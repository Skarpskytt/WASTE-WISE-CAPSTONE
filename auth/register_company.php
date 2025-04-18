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
        $formData['contact_person'] = trim($_POST['contact_person'] ?? '');
        $formData['email'] = trim($_POST['email'] ?? '');
        $formData['phone'] = trim($_POST['phone'] ?? '');
        $formData['address'] = trim($_POST['address'] ?? '');
        $formData['city'] = trim($_POST['city'] ?? '');
        $formData['postal_code'] = trim($_POST['postal_code'] ?? '');
        $formData['business_permit_number'] = trim($_POST['business_permit_number'] ?? '');
        $formData['username'] = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        // Basic validations
        if (empty($formData['company_name'])) $errors[] = "Company name is required";
        if (empty($formData['contact_person'])) $errors[] = "Contact person name is required";
        if (empty($formData['email'])) $errors[] = "Email is required";
        if (empty($formData['phone'])) $errors[] = "Phone number is required";
        if (empty($formData['address'])) $errors[] = "Address is required";
        if (empty($formData['city'])) $errors[] = "City is required";
        if (empty($formData['postal_code'])) $errors[] = "Postal code is required";
        if (empty($formData['business_permit_number'])) $errors[] = "Business permit number is required";
        if (empty($formData['username'])) $errors[] = "Username is required";
        if (empty($password)) $errors[] = "Password is required";
        if ($password !== $passwordConfirm) $errors[] = "Passwords do not match";
        if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters long";
        
        // Check if username is already taken
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$formData['username']]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "Username is already taken";
        }
        
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
                $uploadDir = '../uploads/permits/';
                
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
                // Create user account
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role, created_at) 
                    VALUES (?, ?, ?, 'company', NOW())
                ");
                $stmt->execute([$formData['username'], $formData['email'], $hashedPassword]);
                $userId = $pdo->lastInsertId();
                
                // Create branch record (instead of company profile)
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
                    $formData['contact_person'],
                    $formData['phone']
                ]);
                
                // Add notification for admin
                $branchId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("
                    INSERT INTO notifications 
                    (user_id, target_role, message, notification_type, link, created_at) 
                    VALUES (NULL, 'admin', ?, 'new_company_registration', '/admin/pending_companies.php', NOW())
                ");
                $stmt->execute(["New company registration: " . $formData['company_name']]);
                
                // Update company_request status to registered
                $stmt = $pdo->prepare("
                    UPDATE company_requests 
                    SET status = 'registered', updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$request['id']]);
                
                // Commit transaction
                $pdo->commit();
                
                // Redirect to login page with success message
                header("Location: ../index.php?success=registration_complete");
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
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Contact Person</span>
                            </label>
                            <input type="text" name="contact_person" value="<?= htmlspecialchars($request['contact_person'] ?? '') ?>" class="input input-bordered" required>
                        </div>
                        
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Email</span>
                            </label>
                            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" class="input input-bordered" required readonly>
                            <label class="label">
                                <span class="label-text-alt text-gray-500">Email cannot be changed</span>
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
                    
                    <h3 class="text-xl font-bold text-primarycol">Account Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-control">
                            <label class="label">
                                <span class="label-text">Username</span>
                            </label>
                            <input type="text" name="username" value="<?= htmlspecialchars($formData['username'] ?? '') ?>" class="input input-bordered" required>
                        </div>
                        
                        <div class="form-control md:col-span-2 md:grid md:grid-cols-2 md:gap-6">
                            <div>
                                <label class="label">
                                    <span class="label-text">Password</span>
                                </label>
                                <input type="password" name="password" class="input input-bordered w-full" required minlength="8">
                                <label class="label">
                                    <span class="label-text-alt text-gray-500">At least 8 characters</span>
                                </label>
                            </div>
                            
                            <div>
                                <label class="label">
                                    <span class="label-text">Confirm Password</span>
                                </label>
                                <input type="password" name="password_confirm" class="input input-bordered w-full" required minlength="8">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-control mt-6">
                        <button type="submit" class="btn btn-primary bg-primarycol hover:bg-fourth text-white">Complete Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>