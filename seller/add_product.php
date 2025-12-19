<?php
session_start();
require_once '../config/database.php';

// Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['user_name']; // Needed for sidebar/header
$message = '';
$error = '';

// --- NEW: Flag for Pop-up ---
$show_modal = false; 

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_name = $_POST['product_name'];
    $price = $_POST['price'];
    
    // --- Capture discount price (set to NULL if empty) ---
    $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : NULL;
    // ----------------------------------------------------------

    $stock = $_POST['stock'];
    $category = $_POST['category']; 
    $description = $_POST['description'];
    $brand = $_POST['brand'];
    $gender = $_POST['gender'];
    $notes = $_POST['fragrance_notes'];

    $image_name = '';
    
    // Image Upload Logic
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $filename = $_FILES['image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . "." . $ext;
            $destination = '../images/' . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destination)) {
                $image_name = $new_filename;
            } else {
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid file type. Only JPG, PNG, WEBP allowed.";
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();

            // --- SQL QUERY ---
            $sql = "INSERT INTO products (seller_id, product_name, description, price, discount_price, stock, category, image, product_status, is_active, date_added) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 0, NOW())";
            
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                $seller_id, 
                $product_name, 
                $description, 
                $price, 
                $discount_price, 
                $stock, 
                $category, 
                $image_name
            ]);
            
            $product_id = $pdo->lastInsertId();

            $sql_details = "INSERT INTO product_details (product_id, brand, gender, fragrance_notes) VALUES (?, ?, ?, ?)";
            $stmt_details = $pdo->prepare($sql_details);
            $stmt_details->execute([$product_id, $brand, $gender, $notes]);

            $pdo->commit();
            
            // --- SUCCESS: Set message and trigger Modal ---
            $message = "Product submitted! Please wait for Admin approval.";
            $show_modal = true; 

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>  
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - LaParfume</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login.css">
    
    <style>
        /* --- DASHBOARD LAYOUT CSS (Matches dashboard.php) --- */
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; overflow-x: hidden; min-height: 100vh; }
        
        .sidebar { width: 260px; background: rgba(22, 22, 22, 0.85); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.1); position: fixed; height: 100vh; top: 0; left: 0; padding: 20px; z-index: 100; display: flex; flex-direction: column; }
        
        /* This offsets the content so the sidebar doesn't cover it */
        .main-content { margin-left: 260px; padding: 30px; }
        
        .nav-link { color: #aaa; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background-color: #1dd1a1; color: #000; font-weight: 600; }
        .sidebar .mt-auto { margin-top: auto !important; }

        /* --- FORM SPECIFIC STYLES --- */
        .form-container {
            background: rgba(22, 22, 22, 0.6); 
            backdrop-filter: blur(10px);       
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 40px;
        }

        .form-label { color: #e0e0e0; font-size: 0.9rem; font-weight: 500; margin-bottom: 8px; }
        .form-control, .form-select { background-color: #2b2b2b !important; border: 1px solid #444; color: #fff !important; padding: 12px 15px; border-radius: 8px; }
        .form-control:focus, .form-select:focus { background-color: #333 !important; border-color: #1dd1a1; box-shadow: 0 0 0 0.25rem rgba(29, 209, 161, 0.2); color: #fff !important; }
        
        /* Fix Dropdown Options */
        .form-select option { background-color: #2b2b2b; color: #fff; }

        .category-box { background: rgba(29, 209, 161, 0.05); border: 1px dashed rgba(29, 209, 161, 0.3); padding: 15px; border-radius: 8px; }
        
        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -200px; left: -200px; } .blob-2 { bottom: -200px; right: -200px; }

        /* --- MODAL STYLES --- */
        .modal-content { background: #1a1a1a; border: 1px solid #333; color: white; }
        .modal-header { border-bottom: 1px solid #333; }
        .modal-footer { border-top: 1px solid #333; }
        .btn-close { filter: invert(1); }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <div class="sidebar">
        <div class="mb-5 px-2"><h3 class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Seller</span></h3></div>
        <nav class="nav flex-column">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="add_product.php" class="nav-link active"><i class="fas fa-plus-circle"></i> Add Product</a>
            <a href="my_products.php" class="nav-link"><i class="fas fa-box"></i> My Products</a>
            <a href="stock_logs.php" class="nav-link"><i class="fas fa-history"></i> Stock Logs</a>
            <a href="orders.php" class="nav-link"><i class="fas fa-shopping-bag"></i> Orders</a>
            <a href="my_reviews.php" class="nav-link"><i class="fas fa-star"></i> Reviews</a>
            <a href="withdrawals.php" class="nav-link"><i class="fas fa-wallet"></i> Withdrawals</a>
            <a href="store_settings.php" class="nav-link"><i class="fas fa-store-alt"></i> Store Settings</a>
        </nav>
        <div class="mt-auto"><a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        
        <button class="btn btn-dark d-md-none mb-3" onclick="document.getElementById('sidebar').classList.toggle('active')">
            <i class="fas fa-bars"></i> Menu
        </button>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-white">Add New Product</h2>
                <p class="text-muted">Fill in the details to publish your fragrance</p>
            </div>
           
        </div>

        <?php if($message): ?>
            <div class="alert alert-success d-flex align-items-center d-none"><i class="fas fa-check-circle me-2"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger d-flex align-items-center"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row g-4">
                    
                    <div class="col-md-6">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="product_name" class="form-control" required placeholder="e.g. Chanel No. 5">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Brand</label>
                        <input type="text" name="brand" class="form-control" placeholder="e.g. Chanel">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Regular Price (₱)</label>
                        <input type="number" step="0.01" name="price" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-danger">Sale Price (Optional)</label>
                        <input type="number" step="0.01" name="discount_price" class="form-control" placeholder="Leave empty if not on sale">
                        <small class="text-muted" style="font-size: 0.7rem;">If set lower than Regular Price, it appears in Flash Sales.</small>
                        </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" name="stock" class="form-control" required value="1">
                    </div>

                    <div class="col-12">
                        <div class="category-box mt-2">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" style="color: #1dd1a1;">Shop Category</label>
                                    <select name="category" class="form-select" required>
                                        <option value="" disabled selected>Select Category</option>
                                        <option value="Women">Women's Perfume</option>
                                        <option value="Men">Men's Cologne</option>
                                        <option value="Unisex">Unisex Fragrances</option>
                                        <option value="Gift Sets">Gift Sets</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Gender (Detail)</label>
                                    <select name="gender" class="form-select">
                                        <option value="Female">Female</option>
                                        <option value="Male">Male</option>
                                        <option value="Unisex">Unisex</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Product Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Fragrance Notes</label>
                        <input type="text" name="fragrance_notes" class="form-control" placeholder="e.g. Top: Lemon, Middle: Rose, Base: Vanilla">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Describe the scent profile..."></textarea>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-success w-100 py-3 fw-bold text-uppercase" 
                                style="background-color: #1dd1a1; border: none; color: #000; letter-spacing: 1px;">
                            <i class="fas fa-plus-circle me-2"></i> Publish Product
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="fw-bold text-white">Submitted!</h3>
                    <p class="text-muted">Your product has been submitted successfully and is now pending Admin approval.</p>
                    <button type="button" class="btn btn-success w-100 fw-bold mt-3" data-bs-dismiss="modal" style="background: #1dd1a1; border:none; color:black;">Okay, Got it</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check if PHP set the show_modal flag
        <?php if($show_modal): ?>
            var myModal = new bootstrap.Modal(document.getElementById('successModal'));
            myModal.show();
        <?php endif; ?>
    </script>
</body>
</html>