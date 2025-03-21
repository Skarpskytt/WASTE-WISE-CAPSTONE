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
</head>
<body>
  <div class="flex h-screen">
    <div class="w-full bg-white lg:w-1/2 flex items-center justify-center">
      <div class="max-w-md w-full p-6">
        <h1 class="text-3xl font-semibold mb-6 text-black text-center">Sign Up</h1>
        <h1 class="text-sm font-semibold mb-6 text-gray-500 text-center">Join our community with all-time access and free</h1>
        
        <?php
        session_start();
        if (isset($_SESSION['error'])) {
            echo '<div class="mb-4 text-red-500">' . $_SESSION['error'] . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="mb-4 text-green-500">' . $_SESSION['success'] . '</div>';
            unset($_SESSION['success']);
        }
        ?>
        
        <form action="save_signup.php" method="POST" class="space-y-4">
          <?php if (isset($_SESSION['pending_approval'])): ?>
              <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mt-4" role="alert">
                  <span class="block sm:inline"><?php echo $_SESSION['pending_approval']; ?></span>
              </div>
              <?php unset($_SESSION['pending_approval']); ?>
          <?php endif; ?>
          
          <div class="flex flex-row gap-2">
            <div>
              <label for="fname" class="block text-sm font-medium text-gray-700">First Name</label>
              <input type="text" id="fname" name="fname" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
            </div>
            <div>
              <label for="lname" class="block text-sm font-medium text-gray-700">Last Name</label>
              <input type="text" id="lname" name="lname" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
            </div>
          </div>
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" id="email" name="email" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
          </div>
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
          <div>
            <label for="role" class="block text-sm font-medium text-gray-700">Select Role</label>
            <select id="role" name="role" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300" required>
                <option value="">Select a role</option>
                <option value="branch1_staff">Branch 1 Staff</option>
                <option value="branch2_staff">Branch 2 Staff</option>
                <option value="ngo">NGO Partner</option>
            </select>
          </div>
          <div id="branch-fields" class="hidden">
            <label for="branch" class="block text-sm font-medium text-gray-700">Select Branch</label>
            <select id="branch" name="branch_id" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300">
                <option value="">Select a branch</option>
                <?php
                include('../config/db_connect.php');
                $stmt = $pdo->query('SELECT id, name FROM branches');
                while ($branch = $stmt->fetch()) {
                    echo '<option value="' . htmlspecialchars($branch['id']) . '">' . 
                         htmlspecialchars($branch['name']) . '</option>';
                }
                ?>
            </select>
          </div>
          <div id="ngo-fields" class="hidden space-y-4">
            <div>
                <label for="org_name" class="block text-sm font-medium text-gray-700">Organization Name</label>
                <input type="text" id="org_name" name="org_name" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300">
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">Contact Phone</label>
                <input type="tel" id="phone" name="phone" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300">
            </div>
            <div>
                <label for="address" class="block text-sm font-medium text-gray-700">Organization Address</label>
                <textarea id="address" name="address" rows="3" class="mt-1 p-2 w-full border rounded-md focus:border-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sec transition-colors duration-300"></textarea>
            </div>
          </div>
          <div class="flex gap-2">
            <input type="checkbox" id="terms" name="terms" required>
            <label for="terms" class="text-xs text-gray-600 hover:text-gray-800">Accept Terms and Conditions</label>
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
    <div class="hidden lg:flex items-center justify-center flex-1 bg-sec text-black">
      <div class="max-w-md">
        <img src="../assets/images/isometric-recycling-plastic-and-making-shoes.gif" alt="">
      </div>
    </div>
  </div>
  <script>
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

    function checkPasswordStrength(password) {
        const strengthDiv = document.getElementById('password-strength');
        const strength = {
            1: { text: 'Weak', color: 'text-red-500' },
            2: { text: 'Medium', color: 'text-yellow-500' },
            3: { text: 'Strong', color: 'text-green-500' }
        };

        let strengthScore = 0;

        // Check length
        if (password.length >= 8) strengthScore++;
        
        // Check for mix of characters
        if (password.match(/[a-z]/) && password.match(/[A-Z]/) && password.match(/[0-9]/)) strengthScore++;
        
        // Check for special characters
        if (password.match(/[^a-zA-Z0-9]/)) strengthScore++;

        // Display result
        const result = strength[strengthScore] || strength[1];
        strengthDiv.className = `mt-1 text-xs ${result.color}`;
        strengthDiv.textContent = `Password Strength: ${result.text}`;
    }

    function checkPasswordMatch() {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('conpassword').value;
        const matchDiv = document.getElementById('password-match');

        if (confirmPassword === '') {
            matchDiv.className = 'mt-1 text-xs text-gray-500';
            matchDiv.textContent = '';
        } else if (password === confirmPassword) {
            matchDiv.className = 'mt-1 text-xs text-green-500';
            matchDiv.textContent = 'Passwords match!';
        } else {
            matchDiv.className = 'mt-1 text-xs text-red-500';
            matchDiv.textContent = 'Passwords do not match!';
        }
    }

    document.getElementById('role').addEventListener('change', function() {
        const ngoFields = document.getElementById('ngo-fields');
        const branchFields = document.getElementById('branch-fields');
        
        // Hide all conditional fields first
        ngoFields.classList.add('hidden');
        branchFields.classList.add('hidden');
        
        // Reset required attributes
        document.querySelectorAll('#ngo-fields input, #ngo-fields textarea').forEach(el => el.required = false);
        document.querySelector('#branch').required = false;
        
        // Show relevant fields based on role
        if (this.value === 'ngo') {
            ngoFields.classList.remove('hidden');
            document.querySelectorAll('#ngo-fields input, #ngo-fields textarea').forEach(el => el.required = true);
        } else if (this.value.includes('staff')) {
            branchFields.classList.remove('hidden');
            document.querySelector('#branch').required = true;
        }
    });
  </script>
</body>
</html>