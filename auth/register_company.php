<?php
require_once '../config/db_connect.php';

// Check if token and email exist in URL
if (!isset($_GET['token']) || !isset($_GET['email']) || empty($_GET['token']) || empty($_GET['email'])) {
    header("Location: ../pages/error.php?message=Invalid or missing registration link");
    exit();
}

$token = $_GET['token'];
$email = $_GET['email'];

try {
    $pdo = getPDO();
    
    // Verify token and email combination
    $stmt = $pdo->prepare("SELECT * FROM company_requests WHERE email = ? AND token = ? AND status = 'approved'");
    $stmt->execute([$email, $token]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        header("Location: ../pages/error.php?message=Invalid registration link or request not approved");
        exit();
    }
    
    // Check if token is expired (48 hours)
    $updatedAt = new DateTime($request['updated_at']);
    $now = new DateTime();
    $diff = $now->diff($updatedAt);
    $hoursDiff = $diff->h + ($diff->days * 24);
    
    if ($hoursDiff > 48) {
        header("Location: ../pages/error.php?message=Registration link has expired. Please contact support.");
        exit();
    }
    
    // Check if this company has already registered
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'company'");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        header("Location: ../index.php?error=already_registered");
        exit();
    }
    
    // If form is submitted
    $errors = [];
    $formData = [];
    $success = false;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate form data
        $formData['company_name'] = trim($_POST['company_name'] ?? '');
        $formData['fname'] = trim($_POST['fname'] ?? '');
        $formData['lname'] = trim($_POST['lname'] ?? '');
        $formData['email'] = trim($_POST['email'] ?? '');
        $formData['phone'] = trim($_POST['phone'] ?? '');
        $formData['address'] = trim($_POST['address'] ?? '');
        $formData['city'] = trim($_POST['city'] ?? '');
        $formData['postal_code'] = trim($_POST['postal_code'] ?? '');
        $formData['business_permit_number'] = trim($_POST['business_permit_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        // Basic validations
        if (empty($formData['company_name'])) $errors[] = "Company name is required";
        if (empty($formData['fname'])) $errors[] = "First name is required";
        if (empty($formData['lname'])) $errors[] = "Last name is required";
        if (empty($formData['email'])) $errors[] = "Email is required";
        if (empty($formData['phone'])) $errors[] = "Phone number is required";
        if (empty($formData['address'])) $errors[] = "Address is required";
        if (empty($formData['city'])) $errors[] = "City is required";
        if (empty($formData['postal_code'])) $errors[] = "Postal code is required";
        if (empty($formData['business_permit_number'])) $errors[] = "Business permit number is required";
        if (empty($password)) $errors[] = "Password is required";
        if ($password !== $passwordConfirm) $errors[] = "Passwords do not match";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long";
        
        // Add password complexity validation
        if (!preg_match('/[A-Z]/', $password)) $errors[] = "Password must contain at least one uppercase letter";
        if (!preg_match('/[a-z]/', $password)) $errors[] = "Password must contain at least one lowercase letter";
        if (!preg_match('/[0-9]/', $password)) $errors[] = "Password must contain at least one number";
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = "Password must contain at least one special character";

        // Handle business permit file upload
        $permitFileName = null;
        if (isset($_FILES['business_permit']) && $_FILES['business_permit']['error'] === UPLOAD_ERR_OK) {
            $permitFile = $_FILES['business_permit'];
            $fileExtension = strtolower(pathinfo($permitFile['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                $errors[] = "Business permit must be a PDF, JPG, JPEG, or PNG file";
            } else if ($permitFile['size'] > 5000000) { // 5MB limit
                $errors[] = "Business permit file size must be less than 5MB";
            } else {
                $permitFileName = uniqid('permit_') . '.' . $fileExtension;
                // Changed from '../uploads/permits/' to '../assets/uploads/verification/'
                $uploadDir = '../assets/uploads/verification/';
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $uploadPath = $uploadDir . $permitFileName;
                
                if (!move_uploaded_file($permitFile['tmp_name'], $uploadPath)) {
                    $errors[] = "Failed to upload business permit";
                }
            }
        } else {
            $errors[] = "Business permit document is required";
        }
        
        // If no errors, create the user and branch
        if (empty($errors)) {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Create user account (using email for login instead of username)
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (fname, lname, email, password, role, created_at) 
                    VALUES (?, ?, ?, ?, 'company', NOW())
                ");
                $stmt->execute([$formData['fname'], $formData['lname'], $formData['email'], $hashedPassword]);
                $userId = $pdo->lastInsertId();
                
                // Set the contact person name using first and last name
                $contactPerson = $formData['fname'] . ' ' . $formData['lname'];
                
                // Create branch record
                $branchAddress = $formData['address'] . ', ' . $formData['city'] . ', ' . $formData['postal_code'];
                $stmt = $pdo->prepare("
                    INSERT INTO branches (
                        name, address, location, user_id, business_permit_number, business_permit_file, 
                        contact_person, phone, created_at, branch_type, approval_status
                    ) VALUES (?, ?, 'Main Location', ?, ?, ?, ?, ?, NOW(), 'company_main', 'pending')
                ");
                $stmt->execute([
                    $formData['company_name'],
                    $branchAddress,
                    $userId,
                    $formData['business_permit_number'],
                    $permitFileName,
                    $contactPerson,
                    $formData['phone']
                ]);
                
                // Get the branch ID and update the user record with it
                $branchId = $pdo->lastInsertId();
                $updateUserStmt = $pdo->prepare("UPDATE users SET branch_id = ? WHERE id = ?");
                $updateUserStmt->execute([$branchId, $userId]);

                // Continue with notifications...
                $stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, target_role, message, notification_type, link, created_at) 
                    VALUES (NULL, 'admin', ?, 'company_registration_completed', '/admin/branch_details.php?id=" . $branchId . "', NOW())
                ");
                $stmt->execute(["Company registration completed: " . $formData['company_name']]);
                
                // Update company_request status to registered
                $stmt = $pdo->prepare("
                    UPDATE company_requests 
                    SET status = 'registered', updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$request['id']]);
                
                // Add notification for admin about registration needing approval
                $branchId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, target_role, message, notification_type, link, created_at) 
                    VALUES (NULL, 'admin', ?, 'company_registration_pending', '/admin/pending_companies.php', NOW())
                ");
                $stmt->execute(["New company registration pending approval: " . $formData['company_name']]);

                // Commit transaction
                $pdo->commit();
                
                // Redirect to login page with pending message
                header("Location: ../index.php?pending_approval=Your registration is complete. Please wait for admin approval before logging in.");
                exit();
                
            } catch (Exception $e) {
                // Roll back transaction on error
                $pdo->rollBack();
                $errors[] = "An error occurred during registration: " . $e->getMessage();
                
                // Delete the uploaded file if it exists
                if ($permitFileName && file_exists($uploadDir . $permitFileName)) {
                    unlink($uploadDir . $permitFileName);
                }
            }
        }
    }
    
} catch (PDOException $e) {
    // Log the error and show a user-friendly message
    error_log("Database error: " . $e->getMessage());
    header("Location: ../pages/error.php?message=A system error occurred");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Registration | WasteWise</title>
    <link rel="icon" type="image/x-icon" href="../assets/images/Logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
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
           }
         }
       }
     }
    </script>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-image: url('../assets/images/bg-pattern.png');
            background-size: cover;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto py-12 px-4">
        <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-primarycol to-fourth p-6">
                <div class="flex items-center justify-center mb-4">
                    <img src="../assets/images/Logo.png" alt="WasteWise Logo" class="h-16">
                    <h1 class="text-3xl font-bold text-white ml-4">WasteWise</h1>
                </div>
                <h2 class="text-2xl text-white text-center">Complete Your Company Registration</h2>
            </div>
            
            <div class="p-8">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <ul>
                            <?php foreach($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <p class="mb-6 text-gray-700">Your company application has been approved. Please complete your registration by providing the following information:</p>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-6">
                    <h3 class="text-xl font-bold text-primarycol">Company Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Company Name</span>
                            </label>
                            <input type="text" name="company_name" value="<?= htmlspecialchars($request['company_name'] ?? '') ?>" class="input input-bordered" required>
                        </div>
                        
                        <div class="form-control md:col-span-2 md:grid md:grid-cols-2 md:gap-6">
                            <div>
                                <label class="label">
                                    <span class="label-text">First Name</span>
                                </label>
                                <input type="text" name="fname" value="<?= htmlspecialchars($formData['fname'] ?? '') ?>" class="input input-bordered w-full" required>
                            </div>
                            
                            <div>
                                <label class="label">
                                    <span class="label-text">Last Name</span>
                                </label>
                                <input type="text" name="lname" value="<?= htmlspecialchars($formData['lname'] ?? '') ?>" class="input input-bordered w-full" required>
                            </div>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Email</span>
                            </label>
                            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" class="input input-bordered" required readonly>
                            <label class="label">
                                <span class="label-text-alt text-gray-500">This email will be used for login</span>
                            </label>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Phone Number</span>
                            </label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($request['phone'] ?? '') ?>" class="input input-bordered" required>
                        </div>
                        
                        <div class="form-control md:col-span-2">
                            <label class="label">
                                <span class="label-text">Address</span>
                            </label>
                            <input type="text" name="address" value="<?= htmlspecialchars($formData['address'] ?? '') ?>" class="input input-bordered" required>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">City</span>
                            </label>
                            <input type="text" name="city" value="<?= htmlspecialchars($formData['city'] ?? '') ?>" class="input input-bordered" required>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Postal Code</span>
                            </label>
                            <input type="text" name="postal_code" value="<?= htmlspecialchars($formData['postal_code'] ?? '') ?>" class="input input-bordered" required>
                        </div>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <h3 class="text-xl font-bold text-primarycol">Business Permit Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Business Permit Number</span>
                            </label>
                            <input type="text" name="business_permit_number" value="<?= htmlspecialchars($formData['business_permit_number'] ?? '') ?>" class="input input-bordered" required>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Business Permit Document</span>
                            </label>
                            <input type="file" name="business_permit" class="file-input file-input-bordered w-full" accept=".pdf,.jpg,.jpeg,.png" required>
                            <label class="label">
                                <span class="label-text-alt text-gray-500">Upload PDF, JPG, JPEG, or PNG (Max: 5MB)</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="divider"></div>
                    
                    <h3 class="text-xl font-bold text-primarycol">Account Password</h3>
                    <div class="grid grid-cols-1 gap-6">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Password</span>
                                <button type="button" id="generatePasswordBtn" class="btn btn-sm btn-secondary">Generate Strong Password</button>
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password" class="input input-bordered w-full pr-10" required minlength="8">
                                <button type="button" id="togglePasswordBtn" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <div id="passwordStrength" class="mt-2">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-gray-500 h-2.5 rounded-full" style="width: 0%"></div>
                                </div>
                                <p class="text-xs mt-1 text-gray-500">Strength: <span id="strengthText">None</span></p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mt-2">
                                <div class="text-xs" id="reqLength"><span class="text-red-500">✗</span> At least 8 characters</div>
                                <div class="text-xs" id="reqUppercase"><span class="text-red-500">✗</span> Uppercase letter</div>
                                <div class="text-xs" id="reqLowercase"><span class="text-red-500">✗</span> Lowercase letter</div>
                                <div class="text-xs" id="reqNumber"><span class="text-red-500">✗</span> Number</div>
                                <div class="text-xs" id="reqSpecial"><span class="text-red-500">✗</span> Special character</div>
                            </div>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Confirm Password</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="password_confirm" name="password_confirm" class="input input-bordered w-full pr-10" required minlength="8">
                                <button type="button" id="toggleConfirmBtn" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                                        <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                            <p id="matchMessage" class="text-xs mt-1 invisible">Passwords match</p>
                        </div>
                    </div>
                    
                    <div class="form-control mt-6">
                        <button type="submit" class="btn btn-primary bg-primarycol hover:bg-fourth text-white">Complete Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirm');
    const strengthBar = document.querySelector('#passwordStrength div');
    const strengthText = document.getElementById('strengthText');
    const generateBtn = document.getElementById('generatePasswordBtn');
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    const toggleConfirmBtn = document.getElementById('toggleConfirmBtn');
    const matchMessage = document.getElementById('matchMessage');
    
    // Password requirement indicators
    const reqLength = document.getElementById('reqLength');
    const reqUppercase = document.getElementById('reqUppercase');
    const reqLowercase = document.getElementById('reqLowercase');
    const reqNumber = document.getElementById('reqNumber');
    const reqSpecial = document.getElementById('reqSpecial');
    
    // Toggle password visibility
    togglePasswordBtn.addEventListener('click', () => togglePasswordVisibility(passwordInput, togglePasswordBtn));
    toggleConfirmBtn.addEventListener('click', () => togglePasswordVisibility(confirmInput, toggleConfirmBtn));
    
    // Generate strong password
    generateBtn.addEventListener('click', function() {
        const password = generateStrongPassword();
        passwordInput.value = password;
        confirmInput.value = password;
        checkPasswordStrength();
        checkPasswordMatch();
        
        // Show password briefly when generated
        passwordInput.type = 'text';
        confirmInput.type = 'text';
        setTimeout(() => {
            passwordInput.type = 'password';
            confirmInput.type = 'password';
        }, 3000);
    });
    
    // Check password strength and match
    passwordInput.addEventListener('input', checkPasswordStrength);
    confirmInput.addEventListener('input', checkPasswordMatch);
    
    function checkPasswordStrength() {
        const password = passwordInput.value;
        const hasLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);
        
        // Update requirement indicators
        updateRequirement(reqLength, hasLength);
        updateRequirement(reqUppercase, hasUppercase);
        updateRequirement(reqLowercase, hasLowercase);
        updateRequirement(reqNumber, hasNumber);
        updateRequirement(reqSpecial, hasSpecial);
        
        // Calculate strength
        let strength = 0;
        if (hasLength) strength += 20;
        if (hasUppercase) strength += 20;
        if (hasLowercase) strength += 20;
        if (hasNumber) strength += 20;
        if (hasSpecial) strength += 20;
        
        // Update strength bar
        strengthBar.style.width = strength + '%';
        
        // Set color based on strength
        if (strength < 40) {
            strengthBar.className = 'bg-red-500 h-2.5 rounded-full';
            strengthText.textContent = 'Weak';
            strengthText.className = 'text-red-500';
        } else if (strength < 80) {
            strengthBar.className = 'bg-yellow-500 h-2.5 rounded-full';
            strengthText.textContent = 'Medium';
            strengthText.className = 'text-yellow-500';
        } else {
            strengthBar.className = 'bg-green-500 h-2.5 rounded-full';
            strengthText.textContent = 'Strong';
            strengthText.className = 'text-green-500';
        }
        
        checkPasswordMatch();
    }
    
    function checkPasswordMatch() {
        if (confirmInput.value === '') {
            matchMessage.classList.add('invisible');
            return;
        }
        
        if (passwordInput.value === confirmInput.value) {
            matchMessage.textContent = 'Passwords match';
            matchMessage.className = 'text-xs mt-1 text-green-500';
        } else {
            matchMessage.textContent = 'Passwords do not match';
            matchMessage.className = 'text-xs mt-1 text-red-500';
        }
        matchMessage.classList.remove('invisible');
    }
    
    function updateRequirement(element, isMet) {
        const icon = element.querySelector('span');
        if (isMet) {
            icon.textContent = '✓';
            icon.className = 'text-green-500';
        } else {
            icon.textContent = '✗';
            icon.className = 'text-red-500';
        }
    }
    
    function generateStrongPassword() {
        const length = 12;
        const charset = {
            uppercase: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            lowercase: 'abcdefghijklmnopqrstuvwxyz',
            numbers: '0123456789',
            special: '!@#$%^&*()_+-=[]{}|;:,.<>?'
        };
        
        let password = '';
        
        // Ensure at least one character from each set
        password += charset.uppercase.charAt(Math.floor(Math.random() * charset.uppercase.length));
        password += charset.lowercase.charAt(Math.floor(Math.random() * charset.lowercase.length));
        password += charset.numbers.charAt(Math.floor(Math.random() * charset.numbers.length));
        password += charset.special.charAt(Math.floor(Math.random() * charset.special.length));
        
        // Fill the rest with random characters
        const allChars = charset.uppercase + charset.lowercase + charset.numbers + charset.special;
        for (let i = password.length; i < length; i++) {
            password += allChars.charAt(Math.floor(Math.random() * allChars.length));
        }
        
        // Shuffle the password
        return password.split('').sort(() => 0.5 - Math.random()).join('');
    }
    
    function togglePasswordVisibility(input, button) {
        if (input.type === 'password') {
            input.type = 'text';
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd" />
                <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z" />
            </svg>`;
        } else {
            input.type = 'password';
            button.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor">
                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z" />
                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
            </svg>`;
        }
    }
});
</script>
</body>
</html>