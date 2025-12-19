<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];
$message = '';
$error = '';
$show_modal = false; // Initialize Modal Flag

// --- 1. HANDLE ACTION (Soft Delete & Restore) ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    $new_status = ($action === 'delete') ? 0 : 1; // 0 = Inactive, 1 = Active

    // Security: Ensure this product belongs to this seller
    $check = $pdo->prepare("SELECT product_id FROM products WHERE product_id = ? AND seller_id = ?");
    $check->execute([$id, $seller_id]);
    
    if ($check->rowCount() > 0) {
        $stmt = $pdo->prepare("UPDATE products SET is_active = ? WHERE product_id = ?");
        if ($stmt->execute([$new_status, $id])) {
            $message = ($action === 'delete') ? "Product archived successfully." : "Product restored successfully.";
            // We use a small JS redirect to clear the URL parameters
            echo "<script>window.location.href='my_products.php?tab=" . ($action === 'delete' ? 'active' : 'archived') . "';</script>";
            exit();
        }
    } else {
        $error = "Unauthorized action.";
    }
}

// --- 2. HANDLE RESTOCK (FIXED: Now adds to stock_logs) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restock_product_id'])) {
    $pid = $_POST['restock_product_id'];
    $qty = intval($_POST['quantity_to_add']);
    
    if ($qty > 0) {
        try {
            // 1. Update the actual Product Stock
            $stmt = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ? AND seller_id = ?");
            
            if ($stmt->execute([$qty, $pid, $seller_id])) {
                
                // 2. INSERT RECORD INTO STOCK LOGS
                $log_sql = "INSERT INTO stock_logs (product_id, seller_id, quantity_added) VALUES (?, ?, ?)";
                $log_stmt = $pdo->prepare($log_sql);
                $log_stmt->execute([$pid, $seller_id, $qty]);

                $message = "Stock updated successfully!";
                $show_modal = true; 
            }
        } catch (Exception $e) { $error = "Error: " . $e->getMessage(); }
    }
}

// --- 3. FETCH PRODUCTS BASED ON TAB ---
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'active';
$status_filter = ($current_tab === 'archived') ? 0 : 1;

$stmt = $pdo->prepare("SELECT * FROM products WHERE seller_id = ? AND is_active = ? ORDER BY date_added DESC");
$stmt->execute([$seller_id, $status_filter]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Products - LaParfume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login.css">
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; min-height: 100vh; overflow-x: hidden; }
        .sidebar { width: 260px; background: rgba(22, 22, 22, 0.85); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.1); position: fixed; height: 100vh; top: 0; left: 0; padding: 20px; z-index: 100; display: flex; flex-direction: column; }
        .main-content { margin-left: 260px; padding: 30px; }
        .nav-link { color: #aaa; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background-color: #1dd1a1; color: #000; font-weight: 600; }
        .sidebar .mt-auto { margin-top: auto !important; }
        .table-custom { background: rgba(22, 22, 22, 0.6); backdrop-filter: blur(5px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; overflow: hidden; width: 100%; }
        .table-custom th { background: rgba(34, 34, 34, 0.9); color: #fff; border: none; padding: 15px; }
        .table-custom td { background: transparent; color: #ccc; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 15px; vertical-align: middle; }
        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -200px; left: -200px; } .blob-2 { bottom: -200px; right: -200px; }
        /* Modal */
        .modal-content { background-color: #161616; color: white; border: 1px solid #333; }
        .modal-header, .modal-footer { border-color: #333; }
        .btn-close { filter: invert(1); }
        
        /* Tabs */
        .nav-tabs { border-bottom: 1px solid #333; margin-bottom: 20px; }
        .nav-tabs .nav-link { color: #aaa; border: none; background: transparent; }
        .nav-tabs .nav-link.active { color: #1dd1a1; border-bottom: 2px solid #1dd1a1; font-weight: bold; background: transparent; }
        .nav-tabs .nav-link:hover { color: #fff; }
    </style>
</head>
<body>
    <div class="glow-blob blob-1"></div><div class="glow-blob blob-2"></div>

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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h2 class="fw-bold text-white">My Inventory</h2><p class="text-muted">Manage your product listings and stock</p></div>
            <a href="add_product.php" class="btn btn-success fw-bold" style="background-color: #1dd1a1; border: none; color: black; padding: 10px 25px;"><i class="fas fa-plus me-2"></i> Add Product</a>
        </div>

        <?php if($message && !$show_modal): ?><div class="alert alert-success bg-dark border-success text-success"><?php echo $message; ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-danger bg-dark border-danger text-danger"><?php echo $error; ?></div><?php endif; ?>

        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_tab == 'active') ? 'active' : ''; ?>" href="my_products.php?tab=active">Active Products</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_tab == 'archived') ? 'active' : ''; ?>" href="my_products.php?tab=archived">Archived (Deleted)</a>
            </li>
        </ul>

        <div class="table-responsive">
            <table class="table table-custom">
                <thead><tr><th>Image</th><th>Product Name</th><th>Price</th><th>Stock</th><th>Action</th></tr></thead>
                <tbody>
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><img src="<?php echo !empty($product['image']) ? '../images/' . $product['image'] : 'https://via.placeholder.com/50'; ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;"></td>
                            <td><div class="fw-bold text-white"><?php echo htmlspecialchars($product['product_name']); ?></div><small class="text-muted">ID: #<?php echo $product['product_id']; ?></small></td>
                            
                            <td>
                                <?php if($product['discount_price'] > 0 && $product['discount_price'] < $product['price']): ?>
                                    <div class="text-danger fw-bold">₱<?php echo number_format($product['discount_price'], 2); ?></div>
                                    <small class="text-muted text-decoration-line-through">₱<?php echo number_format($product['price'], 2); ?></small>
                                <?php else: ?>
                                    <span class="text-teal-400 fw-bold">₱<?php echo number_format($product['price'], 2); ?></span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if($product['stock'] <= 5): ?>
                                    <span class="text-danger fw-bold"><?php echo $product['stock']; ?> (Low)</span>
                                <?php else: ?>
                                    <span class="text-white"><?php echo $product['stock']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($current_tab == 'active'): ?>
                                    <button class="btn btn-sm btn-outline-success me-1" data-bs-toggle="modal" data-bs-target="#restockModal" onclick="setRestockData(<?php echo $product['product_id']; ?>, '<?php echo addslashes($product['product_name']); ?>')">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <a href="edit_product.php?id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-outline-light"><i class="fas fa-edit"></i></a>
                                    
                                    <a href="my_products.php?action=delete&id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Archive this product? It will be moved to the Archive tab.');"><i class="fas fa-trash"></i></a>
                                
                                <?php else: ?>
                                    <a href="my_products.php?action=restore&id=<?php echo $product['product_id']; ?>" class="btn btn-sm btn-success fw-bold" style="background: #1dd1a1; border:none; color:black;">
                                        <i class="fas fa-undo me-1"></i> Restore
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No <?php echo $current_tab; ?> products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="restockModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Restock Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="restock_product_id" id="modal_product_id">
                        <div class="mb-3"><label class="form-label text-muted">Product</label><input type="text" class="form-control bg-dark text-white border-secondary" id="modal_product_name" readonly></div>
                        <div class="mb-3"><label class="form-label text-white">Quantity</label><input type="number" name="quantity_to_add" class="form-control bg-dark text-white border-secondary" placeholder="e.g. 50" required min="1"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success" style="background-color: #1dd1a1; border: none; color: black;">Confirm</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="fw-bold text-white">Stock Updated!</h3>
                    <p class="text-muted">The product inventory has been successfully restocked.</p>
                    <button type="button" class="btn btn-success w-100 fw-bold mt-3" data-bs-dismiss="modal" style="background: #1dd1a1; border:none; color:black;">Okay, Got it</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setRestockData(id, name) {
            document.getElementById('modal_product_id').value = id;
            document.getElementById('modal_product_name').value = name;
        }

        <?php if($show_modal): ?>
            var myModal = new bootstrap.Modal(document.getElementById('successModal'));
            myModal.show();
        <?php endif; ?>
    </script>
</body>
</html>