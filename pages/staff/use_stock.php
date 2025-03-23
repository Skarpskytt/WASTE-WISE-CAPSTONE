<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

$pdo = getPDO();

// Get ingredient ID
$ingredientId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$ingredientId) {
    header("Location: ingredients_stocks.php?error=1&message=" . urlencode("Invalid ingredient ID"));
    exit;
}

// Fetch ingredient details
$stmt = $pdo->prepare("SELECT * FROM ingredients WHERE id = ? AND branch_id = ?");
$stmt->execute([$ingredientId, $_SESSION['branch_id']]);
$ingredient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ingredient) {
    header("Location: ingredients_stocks.php?error=1&message=" . urlencode("Ingredient not found"));
    exit;
}

// Get image path
$imgPath = '../../assets/images/default-ingredient.jpg';
if (!empty($ingredient['item_image'])) {
    if (strpos($ingredient['item_image'], 'C:') === 0) {
        // Handle absolute Windows paths
        $filename = basename($ingredient['item_image']);
        $imgPath = 'uploads/ingredients/' . $filename;
    } else if (strpos($ingredient['item_image'], 'uploads/') === 0) {
        // Path already relative, use as-is
        $imgPath = $ingredient['item_image'];
    } else {
        // For any other format, try to use the base filename
        $filename = basename($ingredient['item_image']);
        $imgPath = 'uploads/ingredients/' . $filename;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Use Stock - <?= htmlspecialchars($ingredient['ingredient_name']) ?></title>
  <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
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
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gray-100 flex h-screen">

<?php include (__DIR__ . '/../layout/staff_nav.php'); ?>
<div class="flex-1 p-8 bg-gray-50">
<div class="container mx-auto max-w-8xl">
    <!-- Header Section -->
    <nav class="mb-4">
      <ol class="flex items-center gap-2 text-gray-600 mb-6">
        <li><a href="ingredients.php" class="hover:text-primarycol">Ingredients</a></li>
        <li class="text-gray-400">/</li>
        <li><a href="ingredients_stocks.php" class="hover:text-primarycol">Ingredients Stock</a></li>
        <li class="text-gray-400">/</li>
        <li>Use Stock</li>
      </ol>
    </nav>
    
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold text-primarycol">
        Use Stock: <?= htmlspecialchars($ingredient['ingredient_name']) ?>
      </h1>
      <a href="ingredients_stocks.php" class="btn btn-outline btn-sm hover:bg-primarycol hover:text-white">
        ← Back to Ingredients
      </a>
    </div>
    
    <!-- Main Content Card -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
      <!-- Image and Details Section -->
      <div class="flex flex-col md:flex-row">
        <!-- Image Section -->
        <div class="md:w-1/3 bg-sec p-6 flex items-center justify-center">
          <div class="aspect-square w-full max-w-[250px] rounded-lg overflow-hidden shadow-md">
            <img 
              src="<?= htmlspecialchars($imgPath) ?>" 
              alt="<?= htmlspecialchars($ingredient['ingredient_name']) ?>" 
              class="w-full h-full object-cover"
            />
          </div>
        </div>
        
        <!-- Info Section -->
        <div class="md:w-2/3 p-6">
          <div class="mb-4">
            <span class="inline-block px-2 py-1 text-xs font-semibold bg-gray-200 text-gray-800 rounded-full mb-2">
              <?= htmlspecialchars($ingredient['category'] ?? 'No Category') ?>
            </span>
            <h2 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($ingredient['ingredient_name']) ?></h2>
            <?php if (!empty($ingredient['supplier_name'])): ?>
              <p class="text-gray-600 text-sm">Supplier: <?= htmlspecialchars($ingredient['supplier_name']) ?></p>
            <?php endif; ?>
          </div>
          
          <!-- Stock Information Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-green-50 p-4 rounded-lg border border-green-100">
              <span class="text-gray-600 text-sm">Available Stock</span>
              <p class="text-xl font-semibold text-green-700">
                <?= htmlspecialchars($ingredient['stock_quantity']) ?> 
                <span class="text-sm font-normal"><?= htmlspecialchars($ingredient['unit'] ?? '') ?></span>
              </p>
            </div>
            
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
              <span class="text-gray-600 text-sm">Cost per Unit</span>
              <p class="text-xl font-semibold text-blue-700">
                ₱<?= number_format($ingredient['cost_per_unit'] ?? 0, 2) ?>
                <span class="text-sm font-normal">/ <?= htmlspecialchars($ingredient['unit'] ?? '') ?></span>
              </p>
            </div>
            
            <?php if (!empty($ingredient['expiry_date'])): ?>
            <div class="bg-amber-50 p-4 rounded-lg border border-amber-100">
              <span class="text-gray-600 text-sm">Expiry Date</span>
              <p class="text-xl font-semibold text-amber-700">
                <?= htmlspecialchars(date('M d, Y', strtotime($ingredient['expiry_date']))) ?>
              </p>
            </div>
            <?php else: ?>
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
              <span class="text-gray-600 text-sm">Expiry Date</span>
              <p class="text-xl font-semibold text-gray-700">
                Not specified
              </p>
            </div>
            <?php endif; ?>
          </div>
          
          <!-- Form Section -->
          <form method="POST" action="process_use_stock.php" class="space-y-4">
            <input type="hidden" name="ingredient_id" value="<?= $ingredient['id'] ?>">
            
            <div class="form-control">
              <label class="label">
                <span class="label-text font-medium">Quantity to Use</span>
              </label>
              <div class="flex items-center space-x-2">
                <input type="number" 
                     name="quantity_used" 
                     step="0.01" 
                     min="0.01" 
                     max="<?= $ingredient['stock_quantity'] ?>"
                     class="input input-bordered w-60 focus:ring-2 focus:ring-primarycol" 
                     required>
                <span class="text-gray-600 font-medium min-w-[60px]">
                  <?= htmlspecialchars($ingredient['unit'] ?? '') ?>
                </span>
              </div>
              <label class="label">
                <span class="label-text-alt">Maximum: <?= htmlspecialchars($ingredient['stock_quantity']) ?> <?= htmlspecialchars($ingredient['unit'] ?? '') ?></span>
              </label>
            </div>
            
            <div class="form-control">
              <label class="label">
                <span class="label-text font-medium">Notes (Optional)</span>
              </label>
              <textarea 
                name="notes" 
                class="textarea textarea-bordered h-20 focus:ring-2 focus:ring-primarycol" 
                placeholder="Enter details about how this ingredient will be used (e.g., recipe name, batch number, etc.)"></textarea>
            </div>
            
            <div class="form-control mt-6">
              <button type="submit" 
                  class="btn bg-primarycol hover:bg-fourth text-white w-full md:w-auto px-8">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                Use Stock
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
    
    <!-- Usage Tips Card -->
    <div class="bg-white rounded-lg shadow-md p-6 mt-6">
      <h3 class="text-lg font-semibold text-gray-700 mb-3">Usage Tips</h3>
      <ul class="space-y-2 text-gray-600">
        <li class="flex items-start">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primarycol mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Use this form to record when ingredients are used in recipes or preparations.</span>
        </li>
        <li class="flex items-start">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primarycol mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>If ingredients are wasted rather than used in production, please use the <a href="waste_ingredients_input.php" class="text-primarycol hover:underline">Waste Recording</a> form instead.</span>
        </li>
        <li class="flex items-start">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primarycol mr-2 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>Adding detailed notes helps with tracking ingredient usage patterns over time.</span>
        </li>
      </ul>
    </div>
  </div>
</div>

</body>
</html>