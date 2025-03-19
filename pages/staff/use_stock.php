<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Check for staff access
checkAuth(['staff']);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Use Stock - <?= htmlspecialchars($ingredient['ingredient_name']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="bg-gray-100 flex h-screen">

<?php include (__DIR__ . '/../layout/staff_nav.php'); ?>
<div class="flex-1 p-8 bg-gray-50">
  <div class="container mx-auto max-w-4xl">
    <!-- Header Section -->
    <div class="flex justify-between items-center mb-8">
      <h1 class="text-3xl font-bold text-green-700">
        Use Stock: <?= htmlspecialchars($ingredient['ingredient_name']) ?>
      </h1>
      <a href="ingredients_stocks.php" class="btn btn-outline btn-sm hover:bg-green-700 hover:text-white transition-all">
        ← Back to Ingredients
      </a>
    </div>
    
    <!-- Main Content Card -->
    <div class="bg-white rounded-xl shadow-lg p-8">
      <!-- Stock Information -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8 p-6 bg-gray-50 rounded-lg">
        <div class="flex flex-col">
          <span class="text-gray-500 text-sm">Available Stock</span>
          <span class="text-xl font-semibold">
            <?= htmlspecialchars($ingredient['stock_quantity']) ?> 
            <?= htmlspecialchars($ingredient['unit'] ?? '') ?>
          </span>
        </div>
        <div class="flex flex-col">
          <span class="text-gray-500 text-sm">Cost per Unit</span>
          <span class="text-xl font-semibold">
            ₱<?= number_format($ingredient['cost_per_unit'] ?? 0, 2) ?>
          </span>
        </div>
        <?php if (!empty($ingredient['expiry_date'])): ?>
        <div class="flex flex-col">
          <span class="text-gray-500 text-sm">Expiry Date</span>
          <span class="text-xl font-semibold">
            <?= htmlspecialchars(date('M d, Y', strtotime($ingredient['expiry_date']))) ?>
          </span>
        </div>
        <?php endif; ?>
      </div>
      
      <!-- Form Section -->
      <form method="POST" action="process_use_stock.php" class="space-y-6">
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
                 class="input input-bordered w-full focus:ring-2 focus:ring-green-500" 
                 required>
            <span class="text-gray-600 font-medium min-w-[60px]">
              <?= htmlspecialchars($ingredient['unit'] ?? '') ?>
            </span>
          </div>
        </div>
        
        <div class="form-control">
          <label class="label">
            <span class="label-text font-medium">Notes (Optional)</span>
          </label>
          <textarea 
            name="notes" 
            class="textarea textarea-bordered h-32 focus:ring-2 focus:ring-green-500" 
            placeholder="Enter details about stock usage..."></textarea>
        </div>
        
        <div class="form-control mt-8">
          <button type="submit" 
              class="btn bg-green-600 hover:bg-green-700 text-white w-full md:w-auto px-8">
            Use Stock
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>