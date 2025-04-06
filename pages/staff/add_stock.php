<?php
require_once '../../config/auth_middleware.php';
require_once '../../config/db_connect.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

checkAuth(['staff']);

$pdo = getPDO();
$branchId = $_SESSION['branch_id'];

// Debug POST data
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    error_log('POST data received: ' . print_r($_POST, true));
}

$errors = $_SESSION['form_errors'] ?? [];
$successMessage = $_SESSION['success_message'] ?? '';

// Clear the session variables
unset($_SESSION['form_errors']);
unset($_SESSION['success_message']);

// Get all products from this branch for dropdown
$productsStmt = $pdo->prepare("
    SELECT id, name, category
    FROM product_info 
    WHERE branch_id = ?
    GROUP BY name, category
    ORDER BY name ASC
");
$productsStmt->execute([$branchId]);
$products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique categories for filter dropdown
$categoriesQuery = $pdo->prepare("SELECT DISTINCT category FROM product_info WHERE branch_id = ? ORDER BY category");
$categoriesQuery->execute([$branchId]);
$categories = $categoriesQuery->fetchAll(PDO::FETCH_COLUMN);

// Pagination settings for Products in System section
$productsPerPage = 8; // Number of products per page
$currentPage = isset($_GET['product_page']) ? max(1, intval($_GET['product_page'])) : 1;
$offset = ($currentPage - 1) * $productsPerPage;

// Get products with pagination and sort by stock status - modified sort order for in-stock first
$productsQuery = $pdo->prepare("
    SELECT 
        pi.id,
        pi.name, 
        pi.category, 
        pi.price_per_unit, 
        pi.image,
        pi.unit_type,
        pi.pieces_per_box,
        pi.shelf_life_days,
        COALESCE(SUM(ps.quantity), 0) as total_stock,
        CASE 
            WHEN COALESCE(SUM(ps.quantity), 0) > 10 THEN 1 -- In stock (priority 1)
            WHEN COALESCE(SUM(ps.quantity), 0) > 0 THEN 2  -- Low stock (priority 2)
            ELSE 3                                          -- Out of stock (priority 3)
        END as stock_priority
    FROM product_info pi
    LEFT JOIN product_stock ps ON pi.id = ps.product_info_id
    WHERE pi.branch_id = :branchId
    GROUP BY pi.id, pi.name, pi.category, pi.price_per_unit, pi.image, pi.unit_type, pi.pieces_per_box, pi.shelf_life_days
    ORDER BY stock_priority ASC, total_stock DESC, pi.name ASC
    LIMIT :limit OFFSET :offset
");

$productsQuery->bindParam(':branchId', $branchId, PDO::PARAM_INT);
$productsQuery->bindParam(':limit', $productsPerPage, PDO::PARAM_INT);
$productsQuery->bindParam(':offset', $offset, PDO::PARAM_INT);
$productsQuery->execute();
$allProducts = $productsQuery->fetchAll(PDO::FETCH_ASSOC);

// Get total product count for pagination
$countQuery = $pdo->prepare("
    SELECT COUNT(DISTINCT pi.id) as total
    FROM product_info pi
    WHERE pi.branch_id = ?
");
$countQuery->execute([$branchId]);
$totalProducts = $countQuery->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalProducts / $productsPerPage);

// Function to generate batch number
function generateBatchNumber() {
    $today = date('Ymd');
    $random = mt_rand(1000, 9999);
    return "BB-{$today}-{$random}";
}

// Handle Add Stock Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_stock'])) {
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $stockQuantity = intval($_POST['stock_quantity']);
    $productionDate = $_POST['production_date'] ?? date('Y-m-d');
    $productionTime = $_POST['production_time'] ?? date('H:i'); 
    
    // Use posted batch number or generate a new one
    $batchNumber = !empty($_POST['batch_number']) ? $_POST['batch_number'] : generateBatchNumber();
    
    // Get expiry date from form
    $expiryDate = $_POST['expiry_date'] ?? '';
    
    // If no expiry date is set, calculate one using product's shelf life
    if (empty($expiryDate) && !empty($productionDate)) {
        $productDetailsStmt = $pdo->prepare("SELECT shelf_life_days FROM product_info WHERE id = ? LIMIT 1");
        $productDetailsStmt->execute([$productId]);
        $productDetails = $productDetailsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Use shelf life from product or default to 30 days
        $shelfLifeDays = $productDetails['shelf_life_days'] ?? 30;
        
        // Calculate expiry date
        $expiryDate = date('Y-m-d', strtotime($productionDate . ' + ' . $shelfLifeDays . ' days'));
    }
    
    // Validate inputs
    if ($productId <= 0) {
        $errors[] = "Please select a valid product.";
    }
    
    if ($stockQuantity <= 0) {
        $errors[] = "Stock quantity must be greater than zero.";
    }
    
    if ($_POST['unit_type'] === 'box' && (!isset($_POST['pieces_per_box']) || intval($_POST['pieces_per_box']) <= 0)) {
        $errors[] = "Please specify how many pieces are in each box.";
    }

    if (empty($_POST['expiry_date'])) {
        $errors[] = "Error: Missing expiry date. The selected product may not have shelf life information.";
    }

    if (!empty($_POST['expiry_date']) && !empty($_POST['production_date'])) {
        if (strtotime($_POST['expiry_date']) <= strtotime($_POST['production_date'])) {
            $errors[] = "Error: Expiry date must be after production date.";
        }
    }

    if (empty($_POST['production_time'])) {
        $errors[] = "Production time is required.";
    }

    // If no errors, proceed
    if (empty($errors)) {
        try {
            // Get original product details
            $productStmt = $pdo->prepare("SELECT * FROM product_info WHERE id = ? AND branch_id = ?");
            $productStmt->execute([$productId, $branchId]);
            $productData = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$productData) {
                $errors[] = "Product not found.";
            } else {
                // Debug information - add this to verify values before insertion
                error_log("Inserting stock: Product ID: $productId, Batch: $batchNumber, Qty: $stockQuantity");
                
                // Insert into product_stock
                $stmt = $pdo->prepare("
                    INSERT INTO product_stock (
                        product_info_id, batch_number, quantity, 
                        production_date, expiry_date, best_before,
                        production_time, branch_id, unit_type, pieces_per_box
                    ) VALUES (
                        :productId, :batchNumber, :quantity, 
                        :productionDate, :expiryDate, :bestBefore,
                        :productionTime, :branchId, :unitType, :piecesPerBox
                    )
                ");
                
                // Make sure pieces_per_box is properly handled (default to null if not box)
                $piecesPerBox = ($_POST['unit_type'] === 'box') ? intval($_POST['pieces_per_box']) : null;
                
                $params = [
                    ':productId' => $productId,
                    ':batchNumber' => $batchNumber,
                    ':quantity' => $stockQuantity,
                    ':productionDate' => $productionDate,
                    ':expiryDate' => $expiryDate,
                    ':bestBefore' => $_POST['best_before'] ?? null,
                    ':productionTime' => $productionTime,
                    ':branchId' => $branchId,
                    ':unitType' => $_POST['unit_type'],
                    ':piecesPerBox' => $piecesPerBox
                ];
                
                // Execute with error reporting
                if (!$stmt->execute($params)) {
                    error_log("SQL Error: " . print_r($stmt->errorInfo(), true));
                    throw new PDOException("Failed to insert stock record");
                }
                
                // Set success message in session instead of using AJAX response
                $_SESSION['success_message'] = "Stock added successfully with batch #" . $batchNumber;
                
                // Simple redirect to product_stocks.php
                header("Location: product_stocks.php");
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = "Error: Could not execute the query. " . $e->getMessage();
            error_log("PDO Error: " . $e->getMessage());
            
            // Return error for AJAX requests
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => false, 'error' => $errors[0]]);
                exit;
            }
        }
    } else {
        // Return validation errors for AJAX requests
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            echo json_encode(['success' => false, 'error' => $errors[0]]);
            exit;
        }
    }
    
    // If we got here, there were errors
    // Store errors in session to display after redirect
    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        // Redirect back to the form
        header("Location: add_stock.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Stock - Bea Bakes</title>
    <link rel="icon" type="image/x-icon" href="../../assets/images/Company Logo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@4.12.14/dist/full.min.css" rel="stylesheet" type="text/css" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
    
    // Define closeModal function globally before it's used
    function closeModal() {
        document.getElementById('add-stock-modal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    
    // Define functions for closing edit and archive modals
    function closeEditModal() {
        document.getElementById('edit-product-modal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    
    function closeArchiveModal() {
        document.getElementById('archive-confirmation-modal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    $(document).ready(function() {
        // Generate batch number function
        window.generateBatchNumber = function() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const dateString = `${year}${month}${day}`;
            const random = Math.floor(1000 + Math.random() * 9000);
            
            return `BB-${dateString}-${random}`;
        }
        
        // Search and filter functionality
        const $searchInput = $('#search-products');
        const $categoryFilter = $('#category-filter');
        const $stockFilter = $('#stock-filter');
        const $productCards = $('.product-card');
        
        function filterProducts(e) {
            // If this was triggered by a keypress event in the search field, prevent form submission
            if (e && e.type === 'keypress' && e.which === 13) {
                e.preventDefault();
                return false;
            }
            
            const searchTerm = $searchInput.val().toLowerCase();
            const categoryFilter = $categoryFilter.val();
            const stockFilter = $stockFilter.val();
            
            $productCards.each(function() {
                const $card = $(this);
                const name = $card.data('name').toLowerCase();
                const category = $card.data('category');
                const stockStatus = $card.data('stock-status');
                
                const matchesSearch = name.includes(searchTerm);
                const matchesCategory = !categoryFilter || category === categoryFilter;
                const matchesStock = !stockFilter || stockStatus === stockFilter;
                
                if (matchesSearch && matchesCategory && matchesStock) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
            
            // When filters change, reset to page 1
            if (window.location.href.includes('product_page=')) {
                // Remove product_page parameter and set it to 1
                let newUrl = window.location.href.replace(/product_page=\d+/, 'product_page=1');
                // If product_page wasn't in the URL, add it
                if (!newUrl.includes('product_page=')) {
                    newUrl += (newUrl.includes('?') ? '&' : '?') + 'product_page=1';
                }
                window.history.replaceState({}, '', newUrl);
            }
            
            return false; // Prevent form submission
        }
        
        // Properly attach event handlers to prevent issues
        $searchInput.on('input', filterProducts);
        $searchInput.on('keypress', filterProducts); // Prevent form submission on enter key
        $categoryFilter.on('change', filterProducts);
        $stockFilter.on('change', filterProducts);

        // Prevent any issues with the category filter
        $('#category-filter').on('click', function(e) {
            e.stopPropagation();
        });
        
        // Prevent issues with the options in the dropdown
        $('#category-filter option').on('click', function(e) {
            e.stopPropagation();
            return true;
        });

        // Modal functionality
        // Variables to store modal elements
        const $addStockModal = $('#add-stock-modal');
        const $editModal = $('#edit-product-modal');
        const $archiveModal = $('#archive-confirmation-modal');
        const $closeAddStock = $('#close-modal');
        const $cancelAddStock = $('#modal-cancel');
        const $closeEdit = $('#close-edit-modal');
        const $cancelEdit = $('#cancel-edit-modal');
        const $closeArchive = $('#close-archive-modal');
        const $cancelArchive = $('#cancel-archive-modal');
        
        // Close add stock modal when close button or cancel is clicked
        $closeAddStock.on('click', closeModal);
        $cancelAddStock.on('click', closeModal);
        
        // Close edit modal when close button or cancel is clicked
        $closeEdit.on('click', closeEditModal);
        $cancelEdit.on('click', closeEditModal);
        
        // Close archive modal when close button or cancel is clicked
        $closeArchive.on('click', closeArchiveModal);
        $cancelArchive.on('click', closeArchiveModal);
        
        // Close modals when clicking outside content
        $addStockModal.on('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        $editModal.on('click', function(e) {
            if (e.target === this) closeEditModal();
        });
        
        $archiveModal.on('click', function(e) {
            if (e.target === this) closeArchiveModal();
        });
        
        // Function to close the modals
        window.closeModal = closeModal;
        window.closeEditModal = closeEditModal;
        window.closeArchiveModal = closeArchiveModal;
        
        // Function to open the add stock modal and load product details
        window.openModal = function(productId) {
            // Show loading state
            $addStockModal.removeClass('hidden');
            $('#modal-product-details').addClass('opacity-50');
            $('#modal-product-name').text('Loading...');
            $('#modal-current-stock').text('...');
            
            // Disable scrolling on body
            $('body').addClass('overflow-hidden');
            
            // Generate a new batch number for the modal
            const batchNumber = generateBatchNumber();
            $('#modal-batch-number-display').text(batchNumber);
            $('#modal-batch-number').val(batchNumber);
            
            // Set default production date and time to properly formatted Philippines date
            $('#modal-production-date').val(window.getServerDate());
            $('#modal-production-time').val(window.getServerTime());
            
            // Clear previous quantity
            $('#modal-stock-quantity').val('');
            
            // Fetch product details via AJAX
            $.ajax({
                url: 'get_product_details.php',
                type: 'GET',
                data: { id: productId },
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        console.error("API Error:", data.error);
                        alert("Error loading product details: " + data.error);
                        closeModal();
                        return;
                    }
                    
                    // Set product ID and details
                    $('#modal-product-id').val(productId);
                    $('#modal-product-name').text(data.name);
                    $('#modal-product-category').text(data.category);
                    $('#modal-product-price').text('₱' + parseFloat(data.price_per_unit).toFixed(2));
                    
                    // Update current stock display in modal
                    const currentStock = data.total_stock || '0';
                    $('#modal-current-stock').text(currentStock);
                    
                    // Add appropriate color to stock count
                    if (parseInt(currentStock) <= 0) {
                        $('#modal-current-stock').removeClass('text-blue-700 text-amber-600').addClass('text-red-600');
                    } else if (parseInt(currentStock) < 10) {
                        $('#modal-current-stock').removeClass('text-blue-700 text-red-600').addClass('text-amber-600');
                    } else {
                        $('#modal-current-stock').removeClass('text-red-600 text-amber-600').addClass('text-blue-700');
                    }
                    
                    // Set unit type
                    if (data.unit_type === 'box') {
                        $('#modal-unit-type').val('box');
                        $('#modal-unit-type-text').text('Box/Package');
                        $('#modal-box-size').text(data.pieces_per_box || 12);
                        $('#modal-pieces-per-box').val(data.pieces_per_box || 12);
                        $('#modal-box-info').removeClass('hidden');
                        
                        // Update pieces calculation when quantity changes
                        $('#modal-stock-quantity').off('input').on('input', function() {
                            const boxes = parseInt($(this).val()) || 0;
                            const piecesPerBox = parseInt(data.pieces_per_box) || 12;
                            $('#modal-total-pieces-calc').text(`You're adding ${boxes * piecesPerBox} total pieces.`);
                        });
                    } else {
                        $('#modal-unit-type').val('piece');
                        $('#modal-unit-type-text').text('Individual pieces');
                        $('#modal-box-info').addClass('hidden');
                    }
                    
                    // Set shelf life and calculate expiry
                    $('#modal-product-shelf-life').val(data.shelf_life_days || 30);
                    calculateModalExpiryDate();
                    
                    // Handle image
                    if (data.image) {
                        let imgPath;
                        
                        if (data.image.startsWith('../../')) {
                            imgPath = data.image;
                        } else if (data.image.includes('/')) {
                            imgPath = "../../" + data.image;
                        } else {
                            imgPath = "../../assets/uploads/products/" + data.image;
                        }
                        
                        const img = new Image();
                        img.onload = function() {
                            $('#modal-product-image').attr('src', imgPath);
                        };
                        img.onerror = function() {
                            $('#modal-product-image').attr('src', '../../assets/images/Company Logo.jpg');
                        };
                        img.src = imgPath;
                    } else {
                        $('#modal-product-image').attr('src', '../../assets/images/Company Logo.jpg');
                    }
                    
                    // Remove loading state
                    $('#modal-product-details').removeClass('opacity-50');
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", error);
                    alert('Error fetching product details: ' + error);
                    closeModal();
                }
            });
        }
        
        // Function to open edit product modal
        window.openEditModal = function(productId) {
            // Show modal with loading state
            $editModal.removeClass('hidden');
            $('#edit-product-loading').removeClass('hidden');
            $('#edit-product-form').addClass('hidden');
            
            // Disable body scrolling
            $('body').addClass('overflow-hidden');
            
            // Fetch product details via AJAX
            $.ajax({
                url: 'get_product_details.php',
                type: 'GET',
                data: { id: productId },
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        console.error("API Error:", data.error);
                        alert("Error loading product details: " + data.error);
                        closeEditModal();
                        return;
                    }
                    
                    // Fill form with product data
                    $('#edit-product-id').val(productId);
                    $('#edit-product-name').val(data.name);
                    $('#edit-product-category').val(data.category);
                    $('#edit-product-price').val(data.price_per_unit);
                    $('#edit-product-unit-type').val(data.unit_type);
                    $('#edit-shelf-life').val(data.shelf_life_days || 30);
                    
                    // Handle product image
                    if (data.image) {
                        let imgPath;
                        
                        if (data.image.startsWith('../../')) {
                            imgPath = data.image;
                        } else if (data.image.includes('/')) {
                            imgPath = "../../" + data.image;
                        } else {
                            imgPath = "../../assets/uploads/products/" + data.image;
                        }
                        
                        // Add cache busting parameter
                        const cacheBuster = "?v=" + new Date().getTime();
                        
                        const img = new Image();
                        img.onload = function() {
                            $('#edit-current-image-preview').attr('src', imgPath + cacheBuster);
                        };
                        img.onerror = function() {
                            $('#edit-current-image-preview').attr('src', '../../assets/images/Company Logo.jpg');
                        };
                        img.src = imgPath + cacheBuster;
                        
                        // Store current image path in hidden field
                        $('#current-image-path').val(data.image);
                    } else {
                        $('#edit-current-image-preview').attr('src', '../../assets/images/Company Logo.jpg');
                        $('#current-image-path').val('');
                    }
                    
                    // Handle box specific fields
                    if (data.unit_type === 'box') {
                        $('#edit-box-size-container').removeClass('hidden');
                        $('#edit-pieces-per-box').val(data.pieces_per_box || 12);
                    } else {
                        $('#edit-box-size-container').addClass('hidden');
                    }
                    
                    // Show form, hide loading
                    $('#edit-product-loading').addClass('hidden');
                    $('#edit-product-form').removeClass('hidden');
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", error);
                    alert('Error fetching product details: ' + error);
                    closeEditModal();
                }
            });
        }
        
        // Function to open archive confirmation modal
        window.openArchiveModal = function(productId, productName) {
            // Show modal
            $archiveModal.removeClass('hidden');
            $('body').addClass('overflow-hidden');
            
            // Set product info
            $('#archive-product-id').val(productId);
            $('#archive-product-name').text(productName);
        }
        
        // Handle unit type change in edit modal
        $('#edit-product-unit-type').on('change', function() {
            if ($(this).val() === 'box') {
                $('#edit-box-size-container').removeClass('hidden');
            } else {
                $('#edit-box-size-container').addClass('hidden');
            }
        });
        
        // Handle category selection for new category option
        $('#edit-product-category').on('change', function() {
            if ($(this).val() === 'new') {
                $('#edit-new-category-input').removeClass('hidden');
            } else {
                $('#edit-new-category-input').addClass('hidden');
            }
        });
        
        // Add click handler for Add Stock button
        $('.add-stock-btn').on('click', function(e) {
            e.preventDefault();
            const productId = $(this).data('product-id');
            openModal(productId);
        });
        
        // Add click handler for Edit Product button
        $('.edit-product-btn').on('click', function(e) {
            e.preventDefault();
            const productId = $(this).data('product-id');
            openEditModal(productId);
        });
        
        // Add click handler for Archive Product button
        $('.archive-product-btn').on('click', function(e) {
            e.preventDefault();
            const productId = $(this).data('product-id');
            const productName = $(this).data('product-name');
            openArchiveModal(productId, productName);
        });
        
        // Calculate expiry date in the modal
        window.calculateModalExpiryDate = function() {
            const productionDate = $('#modal-production-date').val();
            const shelfLifeDays = parseInt($('#modal-product-shelf-life').val()) || 30;
            
            if (productionDate) {
                // Create a date object from production date
                const prodDate = new Date(productionDate);
                
                // Add shelf life days for expiry date
                const expiryDate = new Date(prodDate);
                expiryDate.setDate(prodDate.getDate() + shelfLifeDays);
                
                // Format as YYYY-MM-DD
                const formattedDate = expiryDate.toISOString().split('T')[0];
                
                // Update expiry date fields
                $('#modal-expiry-preview').val(formattedDate);
                $('#modal-expiry-date').val(formattedDate);
                
                // Calculate Best Before date (75% of shelf life)
                const bestBeforeDays = Math.floor(shelfLifeDays * 0.75);
                const bestBeforeDate = new Date(prodDate);
                bestBeforeDate.setDate(prodDate.getDate() + bestBeforeDays);
                
                // Format best before date
                const bestBeforeFormatted = bestBeforeDate.toISOString().split('T')[0];
                $('#modal-best-before').val(bestBeforeFormatted);
                
                if (shelfLifeDays <= 0) {
                    $('#modal-expiry-message').text('Warning: Using default 30 days shelf life');
                    $('#modal-expiry-message').addClass('text-amber-500');
                } else {
                    $('#modal-expiry-message').text(`Based on ${shelfLifeDays} days shelf life (Best before: ${bestBeforeFormatted})`);
                    $('#modal-expiry-message').removeClass('text-red-500 text-amber-500');
                }
            }
        }
        
        // Update modal expiry date when production date changes
        $('#modal-production-date').on('change', calculateModalExpiryDate);
        
        // Regenerate batch number when button is clicked
        $('#modal-regenerate-batch').on('click', function(e) {
            e.preventDefault();
            const newBatch = generateBatchNumber();
            $('#modal-batch-number-display').text(newBatch);
            $('#modal-batch-number').val(newBatch);
        });
        
        // Form submission for add stock
        $('#modal-stock-form').on('submit', function(e) {
            // Don't prevent default - we want the form to submit naturally
            
            // Show loading state
            $('#modal-submit').prop('disabled', true).text('Processing...');
            
            // Make sure the add_stock parameter is set
            if (!$('input[name="add_stock"]').length) {
                // If there's no add_stock input field, add one
                $(this).append('<input type="hidden" name="add_stock" value="1">');
            }
            
            // Log what we're submitting
            console.log('Submitting form with data:', $(this).serialize());
            
            // Let the form submit naturally
            return true;
        });
        
        // Form submission for edit product
        $('#edit-product-form').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            // Show loading state
            $('#edit-product-submit').prop('disabled', true).text('Saving...');
            
            // Create form data object to handle file uploads
            const formData = new FormData(this);
            
            // Submit via AJAX
            $.ajax({
                url: 'update_product.php',
                type: 'POST',
                data: formData,
                contentType: false, // Required for FormData
                processData: false, // Required for FormData
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        alert(response.message || 'Product updated successfully!');
                        // Reload page to see the updated product
                        window.location.reload();
                    } else {
                        // Show error
                        alert(response.message || 'Error updating product');
                        $('#edit-product-submit').prop('disabled', false).text('Save Changes');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                    $('#edit-product-submit').prop('disabled', false).text('Save Changes');
                }
            });
        });
        
        // Form submission for archive product
        $('#archive-product-form').on('submit', function(e) {
            // You can add additional confirmation here if needed
        });

        // Prevent all default form submissions except our specific forms
        $(document).on('submit', 'form:not(#modal-stock-form, #edit-product-form, #archive-product-form)', function(e) {
            console.log('Prevented form submission for:', this);
            e.preventDefault();
            return false;
        });
        
        // Prevent enter key from submitting forms unintentionally
        $(document).on('keypress', 'input', function(e) {
            if (e.which === 13 && !$(this).closest('form').is('#modal-stock-form, #edit-product-form, #archive-product-form')) {
                e.preventDefault();
                return false;
            }
        });

        // Add this to your document ready function
        // Preview selected image when a new file is chosen
        $(document).on('change', '#edit-product-image', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#edit-current-image-preview').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);
            }
        });

        // Force image refresh
        $('img').each(function() {
            const originalSrc = $(this).attr('src');
            if (originalSrc && !originalSrc.includes('?v=')) {
                $(this).attr('src', originalSrc + '?v=' + new Date().getTime());
            }
        });
    });
    </script>
    <style>
    /* Image loading state indicators */
    img {
        transition: opacity 0.3s;
    }
    
    img[src='../../assets/images/Company Logo.jpg'] {
        opacity: 0.8;
    }
    
    .img-error {
        border: 1px dashed #eee;
    }
    
    /* Add smooth fade-in effect for images */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    img:not([src='../../assets/images/Company Logo.jpg']) {
        animation: fadeIn 0.5s;
    }
    
    /* Style for add stock button */
    .add-stock-btn {
        transition: all 0.2s;
    }
    
    .add-stock-btn:hover {
        transform: translateY(-1px);
    }
    </style>
</head>

<body class="flex h-screen">
    <?php include ('../layout/staff_nav.php'); ?>

    <div class="p-7 w-full overflow-y-auto">
        <nav class="mb-4">
            <ol class="flex items-center gap-2 text-gray-600">
                <li><a href="product_data.php" class="hover:text-primarycol">Product</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="add_stock.php" class="text-primarycol font-medium">Add Stock</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="product_stocks.php" class="hover:text-primarycol">Product Stocks</a></li>
                <li class="text-gray-400">/</li>
                <li><a href="waste_product_input.php" class="hover:text-primarycol">Record Excess</a></li>
            </ol>
        </nav>
        
        <h1 class="text-3xl font-bold mb-2 text-primarycol">Add Stock to Inventory</h1>
        <p class="text-gray-600 mb-6">Click on any product below to add stock directly</p>

        <!-- Display errors -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Display success message -->
        <?php if (!empty($successMessage)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Product Overview Section -->
        <div class="mt-4">
            <!-- Search and filter bar -->
            <div class="bg-white p-4 rounded-lg shadow mb-6">
                <!-- Add this to prevent form submission when pressing Enter -->
                <form onsubmit="return false;">
                    <div class="flex flex-wrap gap-3">
                        <div class="relative flex-1 min-w-[200px]">
                            <input type="text" id="search-products" placeholder="Search products..." 
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 pl-10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 absolute left-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        
                        <select id="category-filter" class="px-3 py-2 rounded-lg border border-gray-300">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select id="stock-filter" class="px-3 py-2 rounded-lg border border-gray-300">
                            <option value="">All Stock Levels</option>
                            <option value="low">Low Stock</option>
                            <option value="out">Out of Stock</option>
                            <option value="in">In Stock</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Product grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($allProducts as $product): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 hover:shadow-lg transition-shadow product-card" data-name="<?= htmlspecialchars($product['name']) ?>" data-category="<?= htmlspecialchars($product['category']) ?>" data-stock-status="<?= $product['total_stock'] <= 0 ? 'out' : ($product['total_stock'] < 10 ? 'low' : 'in') ?>">
                        <div class="h-48 overflow-hidden relative">
                            <?php 
                            // Fixed image path handling
                            $imagePath = '';
                            if (!empty($product['image'])) {
                                // Check if image is a full path or just a filename
                                if (strpos($product['image'], '/') !== false) {
                                    // Path already has structure
                                    $imagePath = $product['image'];
                                } else {
                                    // Just a filename, append full path
                                    $imagePath = "../../assets/uploads/products/" . $product['image'];
                                }
                            } else {
                                // Use default image
                                $imagePath = "../../assets/images/Company Logo.jpg";
                            }

                            // Add cache busting parameter with last update timestamp
                            $cacheBuster = "?v=" . (isset($product['updated_at']) ? strtotime($product['updated_at']) : time());
                            ?>
                            <img src="<?= htmlspecialchars($imagePath . $cacheBuster) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="w-full h-full object-cover"
                                 onerror="this.onerror=null; this.src='../../assets/images/Company Logo.jpg';">
                            
                            <!-- Stock badge -->
                            <div class="absolute top-2 right-2 px-2 py-1 rounded-full text-xs font-bold
                                <?php if($product['total_stock'] <= 0): ?>
                                    bg-red-100 text-red-800
                                <?php elseif($product['total_stock'] < 10): ?>
                                    bg-amber-100 text-amber-800
                                <?php else: ?>
                                    bg-green-100 text-green-800
                                <?php endif; ?>">
                                <?= $product['total_stock'] <= 0 ? 'Out of Stock' : ($product['total_stock'] < 10 ? 'Low Stock' : 'In Stock') ?>
                            </div>
                        </div>
                        
                        <div class="p-4">
                            <h3 class="font-bold text-lg text-gray-800 truncate"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="text-sm text-gray-600"><?= htmlspecialchars($product['category']) ?></p>
                            
                            <div class="flex justify-between items-center mt-3">
                                <p class="font-medium text-primarycol">₱<?= number_format($product['price_per_unit'], 2) ?></p>
                                <p class="text-sm text-gray-500">
                                    <?= $product['unit_type'] === 'box' ? 'Box of ' . $product['pieces_per_box'] : 'Per piece' ?>
                                </p>
                            </div>
                            
                            <!-- Add current stock count display -->
                            <div class="mt-2 flex justify-between items-center">
                                <div class="text-sm">
                                    <span class="font-medium">Current Stock:</span> 
                                    <span class="<?= $product['total_stock'] <= 0 ? 'text-red-600 font-bold' : ($product['total_stock'] < 10 ? 'text-amber-600 font-medium' : 'text-gray-700') ?>">
                                        <?= $product['total_stock'] ?> <?= $product['unit_type'] === 'box' ? 'boxes' : 'pieces' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-4 pt-3 border-t border-gray-100">
                                <!-- Add Stock Button -->
                                <button 
                                    class="w-full py-2 mb-2 bg-primarycol text-white rounded-md hover:bg-fourth transition-colors add-stock-btn"
                                    data-product-id="<?= $product['id'] ?>"
                                    data-current-stock="<?= $product['total_stock'] ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
                                    </svg>
                                    Add Stock
                                </button>
                                
                                <!-- Action Buttons (Edit & Archive) -->
                                <div class="flex gap-2 mt-2">
                                    <button 
                                        class="flex-1 py-2 bg-amber-500 text-white rounded-md hover:bg-amber-600 transition-colors edit-product-btn"
                                        data-product-id="<?= $product['id'] ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                        </svg>
                                        Edit
                                    </button>
                                    <button 
                                        class="flex-1 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors archive-product-btn"
                                        data-product-id="<?= $product['id'] ?>"
                                        data-product-name="<?= htmlspecialchars($product['name']) ?>">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline-block mr-1" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                                        </svg>
                                        Archive
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <div class="inline-flex rounded-md shadow">
                        <div class="flex">
                            <?php if ($currentPage > 1): ?>
                                <a href="?product_page=<?= $currentPage - 1 ?>" class="px-3 py-2 inline-flex items-center text-sm leading-5 font-medium rounded-l-md text-gray-700 bg-white hover:text-gray-500 focus:outline-none focus:shadow-outline-blue focus:border-blue-300 active:bg-gray-100 active:text-gray-700 transition ease-in-out duration-150">
                                    <svg class="h-5 w-5 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 01-1.414 1.414l-4-4a1 1 010-1.414l4-4a1 1 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?product_page=<?= $i ?>" class="px-4 py-2 inline-flex items-center text-sm leading-5 font-medium <?= $i === $currentPage ? 'bg-primarycol text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?> focus:outline-none transition ease-in-out duration-150">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?product_page=<?= $currentPage + 1 ?>" class="px-3 py-2 inline-flex items-center text-sm leading-5 font-medium rounded-r-md text-gray-700 bg-white hover:text-gray-500 focus:outline-none focus:shadow-outline-blue focus:border-blue-300 active:bg-gray-100 active:text-gray-700 transition ease-in-out duration-150">
                                    Next
                                    <svg class="h-5 w-5 ml-1" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 011.414-1.414l4 4a1 1 010 1.414l-4 4a1 1 01-1.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="text-center text-sm text-gray-600 mt-4">
                    Showing <?= ($currentPage - 1) * $productsPerPage + 1 ?> to <?= min($currentPage * $productsPerPage, $totalProducts) ?> of <?= $totalProducts ?> products
                </div>
            <?php endif; ?>
            
            <!-- User guidance section -->
            <div class="bg-blue-50 p-4 rounded-lg mt-8 border border-blue-200">
                <h3 class="font-medium text-blue-800 mb-2">Quick Tips</h3>
                <ul class="list-disc list-inside text-sm text-blue-700 space-y-1">
                    <li>Click "Add Stock" on any product to add inventory</li>
                    <li>Use the search and filter options to find specific products</li>
                    <li>Products with <span class="bg-red-100 text-red-800 px-1 rounded">Out of Stock</span> need immediate attention</li>
                    <li>Products with <span class="bg-amber-100 text-amber-800 px-1 rounded">Low Stock</span> should be replenished soon</li>
                    <li>Looking for a product that's not listed? <a href="product_data.php" class="underline font-medium">Add a new product here</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Product Stock Modal -->
    <div id="add-stock-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-primarycol">Add Stock</h2>
                    <button id="close-modal" class="text-gray-400 hover:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div id="modal-product-details" class="bg-gray-50 p-4 rounded-lg mb-6 flex gap-4 items-center">
                    <div class="w-24 h-24 bg-gray-200 rounded-lg overflow-hidden flex-shrink-0">
                        <img id="modal-product-image" 
                             src="../../assets/images/Company Logo.jpg" 
                             alt="Product" 
                             class="w-full h-full object-cover">
                    </div>
                    <div class="flex-grow">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-bold text-lg" id="modal-product-name">Product Name</h3>
                                <p class="text-gray-600" id="modal-product-category">Category</p>
                                <p class="font-medium text-primarycol" id="modal-product-price">₱0.00</p>
                            </div>
                            <div class="bg-blue-50 px-3 py-2 rounded-lg flex items-center">
                                <div class="mr-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-700" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM14 11a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-sm font-semibold text-gray-700">Current Stock:</span><br>
                                    <span id="modal-current-stock" class="text-lg font-bold text-blue-700">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script>
                // Set these variables based on PHP server time for more accuracy
                const serverDate = "<?= date('Y-m-d') ?>";
                const serverTime = "<?= date('H:i') ?>";

                // These will be used when the modal is opened
                document.addEventListener('DOMContentLoaded', function() {
                    window.getServerDate = function() {
                        return serverDate;
                    };
                    
                    window.getServerTime = function() {
                        return serverTime;
                    };
                });
                </script>

                <form id="modal-stock-form" class="w-full" method="POST" action="add_stock.php">
                    <input type="hidden" id="modal-product-id" name="product_id" value="">
                    <input type="hidden" name="add_stock" value="1">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div class="mb-4">
                            <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="modal-stock-quantity">
                                Stock Quantity
                            </label>
                            <input type="number" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                                id="modal-stock-quantity" name="stock_quantity" min="1" required />
                        </div>
                        
                        <div class="mb-4" id="modal-unit-type-display">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Unit Type
                            </label>
                            <div id="modal-unit-type-text" class="py-3 px-3 bg-gray-50 rounded-lg">
                                Individual pieces
                            </div>
                            <input type="hidden" id="modal-unit-type" name="unit_type" value="piece">
                            <input type="hidden" id="modal-pieces-per-box" name="pieces_per_box" value="1">
                        </div>

                        <div id="modal-box-info" class="mb-4 hidden bg-blue-50 p-3 rounded-lg">
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-600 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                </svg>
                                <span>This product is tracked in boxes. Each box contains <span id="modal-box-size" class="font-bold">12</span> pieces.</span>
                            </div>
                            <div class="mt-2 text-sm text-blue-700">
                                <span id="modal-total-pieces-calc">You're adding 0 total pieces.</span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2">
                                Batch Number (Auto-Generated)
                            </label>
                            <div class="flex">
                                <div class="flex-grow appearance-none block w-full bg-gray-100 text-gray-700 font-mono border border-gray-400 rounded-l-lg py-3 px-3">
                                    <span id="modal-batch-number-display">BB-YYYYMMDD-XXXX</span>
                                    <input type="hidden" name="batch_number" id="modal-batch-number" value="">
                                </div>
                                <button id="modal-regenerate-batch" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 rounded-r-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Auto-generated batch number</p>
                        </div>

                        <div class="mb-4">
                            <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="modal-production-date">
                                Production Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                                id="modal-production-date" name="production_date" value="<?= date('Y-m-d') ?>" required />
                            <p class="text-xs text-gray-500 mt-1">Date when this batch was produced</p>
                        </div>

                        <div class="mb-4">
                            <label class="block uppercase tracking-wide text-gray-700 text-sm font-bold mb-2" for="modal-production-time">
                                Production Time <span class="text-red-500">*</span>
                            </label>
                            <input type="time" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                                id="modal-production-time" name="production_time" value="<?= date('H:i') ?>" required />
                                Calculated Expiry Date
                            </label>
                            <input type="date" class="appearance-none block w-full bg-gray-100 text-gray-700 font-medium border border-gray-400 rounded-lg py-3 px-3" 
                                id="modal-expiry-preview" readonly />
                            <p class="text-xs text-gray-500 mt-1" id="modal-expiry-message">Automatically calculated based on the product's shelf life</p>
                        </div>

                        <input type="hidden" id="modal-product-shelf-life" name="product_shelf_life" value="0">
                        <input type="hidden" id="modal-expiry-date" name="expiry_date" value="">
                        <input type="hidden" id="modal-best-before" name="best_before" value="">
                    </div>
                    
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" id="modal-cancel" class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                        <button type="submit" name="add_stock" id="modal-submit" class="px-6 py-3 bg-primarycol text-white rounded-lg hover:bg-fourth">
                            Add Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="edit-product-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-primarycol">Edit Product</h2>
                    <button id="close-edit-modal" class="text-gray-400 hover:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div id="edit-product-loading" class="flex justify-center py-6">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primarycol"></div>
                </div>
                
                <form id="edit-product-form" class="w-full hidden" method="POST" action="update_product.php" enctype="multipart/form-data">
                    <input type="hidden" id="edit-product-id" name="product_id" value="">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                        <div class="mb-4">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="edit-product-name">
                                Product Name
                            </label>
                            <input type="text" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                                id="edit-product-name" name="product_name" required />
                        </div>
                        
                        <div class="mb-4">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="edit-product-category">
                                Category
                            </label>
                            <select class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                                id="edit-product-category" name="product_category" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
                                <?php endforeach; ?>
                                <option value="new">+ Add New Category</option>
                            </select>
                        </div>
                        
                        <div id="edit-new-category-input" class="mb-4 hidden">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="edit-new-category">
                                New Category Name
                            </label>
                            <input type="text" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                                id="edit-new-category" name="new_category" />
                        </div>
                        
                        <div class="mb-4">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="edit-product-price">
                                Price Per Unit
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">₱</span>
                                </div>
                                <input type="number" min="0" step="0.01" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 pl-7 pr-3 leading-tight focus:outline-none focus:border-primarycol" 
                                    id="edit-product-price" name="price_per_unit" required />
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="edit-product-unit-type">
                                Unit Type
                            </label>
                            <select class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                                id="edit-product-unit-type" name="unit_type" required>
                                <option value="piece">Individual Pieces</option>
                                <option value="box">Box/Package</option>
                            </select>
                        </div>
                        
                        <div id="edit-box-size-container" class="mb-4 hidden">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="edit-pieces-per-box">
                                Pieces Per Box
                            </label>
                            <input type="number" min="1" step="1" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                                id="edit-pieces-per-box" name="pieces_per_box" value="12" />
                        </div>
                        
                        <div class="mb-4">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="edit-shelf-life">
                                Shelf Life (Days)
                            </label>
                            <input type="number" min="1" class="appearance-none block w-full bg-white text-gray-900 font-medium border border-gray-400 rounded-lg py-3 px-3 leading-tight focus:outline-none focus:border-primarycol" 
                                id="edit-shelf-life" name="shelf_life_days" />
                        </div>
                        
                        <!-- Add image upload section -->
                        <div class="mb-4 md:col-span-2">
                            <label class="block uppercase tracking-wide text-gray-700 text-xs font-bold mb-2" for="edit-product-image">
                                Product Image
                            </label>
                            
                            <div class="flex items-start">
                                <!-- Current image preview -->
                                <div class="w-24 h-24 bg-gray-100 rounded-md overflow-hidden mr-4 flex-shrink-0 border border-gray-200">
                                    <img id="edit-current-image-preview" src="../../assets/images/Company Logo.jpg" alt="Current product image" class="w-full h-full object-cover">
                                </div>
                                
                                <div class="flex-grow">
                                    <input type="file" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" 
                                        id="edit-product-image" name="product_image" accept="image/jpeg,image/png,image/jpg"/>
                                    <p class="text-xs text-gray-500 mt-1">Upload a new image (JPG or PNG, max 2MB)</p>
                                    <input type="hidden" id="current-image-path" name="current_image" value="">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-4 mt-6">
                        <button type="button" id="cancel-edit-modal" class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">Cancel</button>
                        <button type="submit" id="edit-product-submit" class="px-6 py-3 bg-amber-500 text-white rounded-lg hover:bg-amber-600">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Archive Product Confirmation Modal -->
    <div id="archive-confirmation-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-red-600">Archive Product</h2>
                    <button id="close-archive-modal" class="text-gray-400 hover:text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="p-6">
                <div class="text-center mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 mx-auto text-red-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    
                    <h3 class="text-lg font-bold mt-4">Are you sure you want to archive this product?</h3>
                    <p class="text-gray-600 mt-2">
                        You are about to archive <span id="archive-product-name" class="font-bold"></span>.
                        This will hide the product from active inventory but preserve all historical data.
                    </p>
                </div>
                
                <form id="archive-product-form" action="archive_product.php" method="POST">
                    <input type="hidden" id="archive-product-id" name="product_id">
                    <div class="flex justify-center gap-4">
                        <button type="button" id="cancel-archive-modal" class="px-6 py-3 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-3 bg-red-500 text-white rounded-lg hover:bg-red-600">
                            Archive Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>