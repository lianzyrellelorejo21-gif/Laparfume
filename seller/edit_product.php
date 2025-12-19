<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php'); exit();
}

$seller_id = $_SESSION['user_id'];
$id = $_GET['id'] ?? null;
if (!$id) { header('Location: my_products.php'); exit(); }

// Fetch Product
$stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ? AND seller_id = ?");
$stmt->execute([$id, $seller_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { header('Location: my_products.php'); exit(); }

$show_modal = false; // Initialize Modal Flag

// Handle Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['product_name'];
    $price = $_POST['price'];
    
    // --- 1. CAPTURE SALE PRICE ---
    // If empty, we set it to NULL in the database
    $discount_price = !empty($_POST['discount_price']) ? $_POST['discount_price'] : NULL;
    
    $desc = $_POST['description'];
    
    // Optional: Handle Image Update
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if(in_array($ext, $allowed)){
            $new_name = uniqid() . "." . $ext;
            if(move_uploaded_file($file['tmp_name'], "../images/" . $new_name)){
                $stmt = $pdo->prepare("UPDATE products SET image = ? WHERE product_id = ?");
                $stmt->execute([$new_name, $id]);
            }
        }
    }

    // --- 2. UPDATE QUERY (Includes Discount Price) ---
    $stmt = $pdo->prepare("UPDATE products SET product_name=?, price=?, discount_price=?, description=? WHERE product_id=?");
    if ($stmt->execute([$name, $price, $discount_price, $desc, $id])) {
        // --- 3. TRIGGER MODAL INSTEAD OF ALERT ---
        $show_modal = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - LaParfume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; min-height: 100vh; overflow-x: hidden; }
        .text-muted { 
         color: #aaa !important; 
         }
        /* Sidebar */
        .sidebar { width: 260px; background: rgba(22, 22, 22, 0.85); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.1); position: fixed; height: 100vh; top: 0; left: 0; padding: 20px; z-index: 100; display: flex; flex-direction: column; }
        .main-content { margin-left: 260px; padding: 30px; }
        .nav-link { color: #aaa; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background-color: #1dd1a1; color: #000; font-weight: 600; }
        .mt-auto { margin-top: auto !important; }

        /* Main Content */
        .main-content { margin-left: 260px; padding: 40px; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; }
        
        /* Edit Container */
        .edit-container {
            width: 100%;
            max-width: 1000px;
            background: rgba(30, 30, 30, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }

        .section-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 30px; color: #fff; border-left: 4px solid #1dd1a1; padding-left: 15px; }

        /* Grid Layout */
        .edit-grid { display: grid; grid-template-columns: 300px 1fr; gap: 40px; }

        /* Image Upload Section */
        .image-preview-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 1/1;
            border-radius: 15px;
            overflow: hidden;
            border: 2px dashed rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            transition: 0.3s;
        }
        .image-preview-wrapper:hover { border-color: #1dd1a1; }
        .image-preview-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        
        .upload-btn {
            display: block;
            width: 100%;
            padding: 10px;
            text-align: center;
            background: #222;
            border: 1px solid #444;
            color: #ccc;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 0.9rem;
        }
        .upload-btn:hover { background: #333; color: #fff; border-color: #666; }
        input[type="file"] { display: none; }

        /* Form Inputs */
        .form-label { color: #aaa; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; font-weight: 600; }
        .form-control, .form-control:read-only {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 12px 15px;
            border-radius: 8px;
            font-size: 1rem;
        }
            
        .form-control::placeholder {
    		color: #888 !important; /* Light gray color */
    		opacity: 1; /* Fix for Firefox */
		}
            
        .form-control:focus {
            background-color: rgba(255, 255, 255, 0.08);
            border-color: #1dd1a1;
            box-shadow: 0 0 0 4px rgba(29, 209, 161, 0.1);
            color: #fff;
        }
        .input-group-text { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.1); color: #aaa; }

        /* Stock Warning */
        .stock-readonly { background-color: rgba(255, 69, 0, 0.05) !important; border-color: rgba(255, 69, 0, 0.2) !important; color: #ff6b6b !important; cursor: not-allowed; }
        .stock-note { font-size: 0.75rem; color: #888; margin-top: 5px; display: block; }

        /* Buttons */
        .btn-save {
            background: linear-gradient(135deg, #1dd1a1, #10ac84);
            border: none;
            color: #000;
            font-weight: 700;
            padding: 12px 30px;
            border-radius: 8px;
            transition: 0.3s;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(29, 209, 161, 0.3); color: #000; }
        
        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.1) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -150px; left: -150px; } .blob-2 { bottom: -150px; right: -150px; }

        /* Modal Styles */
        .modal-content { background-color: #161616; color: white; border: 1px solid #333; }
        .modal-header { border-bottom: 1px solid #333; }
        .modal-footer { border-top: 1px solid #333; }
        .btn-close { filter: invert(1); }

        @media (max-width: 991px) { .edit-grid { grid-template-columns: 1fr; } .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

     <div class="sidebar">
        <div class="mb-5 px-2"><h3 class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Seller</span></h3></div>
        <nav class="nav flex-column">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="add_product.php" class="nav-link"><i class="fas fa-plus-circle"></i> Add Product</a>
            <a href="my_products.php" class="nav-link active"><i class="fas fa-box"></i> My Products</a>
            <a href="stock_logs.php" class="nav-link"><i class="fas fa-history"></i> Stock Logs</a>
            <a href="orders.php" class="nav-link"><i class="fas fa-shopping-bag"></i> Orders</a>
            <a href="my_reviews.php" class="nav-link"><i class="fas fa-star"></i> Reviews</a>
            <a href="withdrawals.php" class="nav-link"><i class="fas fa-wallet"></i> Withdrawals</a>
            <a href="store_settings.php" class="nav-link"><i class="fas fa-store-alt"></i> Store Settings</a>
        </nav>
        <div class="mt-auto"><a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>


    <div class="main-content">
        <div class="edit-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="section-title m-0">Edit Product Details</h1>
                <a href="my_products.php" class="btn btn-outline-light btn-sm px-3"><i class="fas fa-arrow-left me-2"></i>Back</a>
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="edit-grid">
                    
                    <div>
                        <label class="form-label">Product Image</label>
                        <div class="image-preview-wrapper">
                            <?php $img = !empty($product['image']) ? '../images/'.$product['image'] : 'https://via.placeholder.com/300x300/111/333?text=No+Image'; ?>
                            <img src="<?php echo $img; ?>" id="imagePreview">
                        </div>
                        <label for="imageInput" class="upload-btn">
                            <i class="fas fa-camera me-2"></i>Change Image
                        </label>
                        <input type="file" name="image" id="imageInput" accept="image/*" onchange="previewFile()">
                        <div class="text-muted small text-center mt-2">Recommended: 800x800 px</div>
                    </div>

                    <div>
                        <div class="mb-4">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="product_name" class="form-control form-control-lg" value="<?php echo htmlspecialchars($product['product_name']); ?>" required>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Original Price</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="price" class="form-control" value="<?php echo $product['price']; ?>" step="0.01" required>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label text-warning">Sale Price (Optional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" name="discount_price" class="form-control" value="<?php echo $product['discount_price']; ?>" step="0.01" placeholder="0.00">
                                </div>
                                <small class="text-muted" style="font-size: 0.7rem;">Leave empty if not on sale.</small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label text-danger">Stock (Read Only)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary"><i class="fas fa-cubes text-muted"></i></span>
                                    <input type="number" class="form-control stock-readonly" value="<?php echo $product['stock']; ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="6" required style="resize: none;"><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>

                        <div class="text-end pt-3 border-top border-secondary">
                            <button type="button" onclick="window.location.href='my_products.php'" class="btn btn-outline-secondary me-2">Cancel</button>
                            <button type="submit" class="btn btn-save"><i class="fas fa-save me-2"></i>Save Changes</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="fw-bold text-white">Success!</h3>
                    <p class="text-muted">Product details have been updated successfully.</p>
                    <a href="my_products.php" class="btn btn-success w-100 fw-bold mt-3" style="background: #1dd1a1; border:none; color:black;">Back to Inventory</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewFile() {
            const preview = document.getElementById('imagePreview');
            const file = document.getElementById('imageInput').files[0];
            const reader = new FileReader();

            reader.addEventListener("load", function () {
                preview.src = reader.result;
            }, false);

            if (file) {
                reader.readAsDataURL(file);
            }
        }

        // TRIGGER MODAL IF PHP FLAG IS TRUE
        <?php if($show_modal): ?>
            var myModal = new bootstrap.Modal(document.getElementById('successModal'));
            myModal.show();
        <?php endif; ?>
    </script>
</body>
</html>