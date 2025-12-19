<?php
session_start();
require_once '../config/database.php';

// 1. Check Seller Login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// 2. Fetch Stock Logs
$sql = "SELECT sl.*, p.product_name, p.image, p.stock as current_stock
        FROM stock_logs sl
        JOIN products p ON sl.product_id = p.product_id
        WHERE sl.seller_id = ?
        ORDER BY sl.date_added DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$seller_id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Stats Logic ---
// 1. Unique Products Count (For Top Widget)
$product_ids = array_column($logs, 'product_id');
$total_products_restocked = count(array_unique($product_ids));

// 2. Total Units Sum (For Table Footer)
$total_units_added = array_sum(array_column($logs, 'quantity_added'));
// -------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock History - Seller Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login.css">
    
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; overflow-x: hidden; }
        .sidebar { width: 260px; background-color: #111; border-right: 1px solid #333; position: fixed; top: 0; left: 0; height: 100vh; padding: 20px; display: flex; flex-direction: column; z-index: 100; }
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; position: relative; z-index: 2; }
        .nav-link { color: #aaa; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background-color: #1dd1a1; color: #000; font-weight: 600; }
        .sidebar .mt-auto { margin-top: auto !important; }

        .stat-card {
            background: linear-gradient(145deg, #1a1a1a, #111);
            border: 1px solid #333; border-radius: 16px; padding: 25px;
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        .stat-icon {
            width: 60px; height: 60px; border-radius: 12px;
            background: rgba(29, 209, 161, 0.1); color: #1dd1a1;
            display: flex; align-items: center; justify-content: center; font-size: 1.8rem;
        }

        .logs-container {
            background: #161616; border: 1px solid #333; border-radius: 16px; overflow: hidden;
        }
        .table-custom { margin-bottom: 0; color: #ccc; }
        .table-custom th { background: #222; color: #fff; text-transform: uppercase; font-size: 0.8rem; padding: 15px 25px; border-bottom: 1px solid #333; }
        .table-custom td { padding: 20px 25px; vertical-align: middle; border-bottom: 1px solid #222; background: transparent; color: #ccc; }
        .table-custom tr:last-child td { border-bottom: none; }
        .table-custom tr:hover td { background: rgba(255,255,255,0.02); }
        
        /* Footer Style */
        .table-custom tfoot td { background: #1a1a1a; border-top: 2px solid #333; color: #fff; padding: 20px 25px; }

        .prod-img { width: 45px; height: 45px; border-radius: 8px; object-fit: cover; margin-right: 15px; }
        .badge-added { background: rgba(46, 204, 113, 0.15); color: #2ecc71; padding: 5px 10px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; }
        
        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.1) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -200px; right: -200px; }
    </style>
</head>
<body>

    <div class="glow-blob blob-1"></div>

   <div class="sidebar">
        <div class="mb-5 px-2"><h3 class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Seller</span></h3></div>
        <nav class="nav flex-column">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="add_product.php" class="nav-link"><i class="fas fa-plus-circle"></i> Add Product</a>
            <a href="my_products.php" class="nav-link"><i class="fas fa-box"></i> My Products</a>
            <a href="stock_logs.php" class="nav-link active"><i class="fas fa-history"></i> Stock Logs</a>
            <a href="orders.php" class="nav-link"><i class="fas fa-shopping-bag"></i> Orders</a>
            <a href="my_reviews.php" class="nav-link"><i class="fas fa-star"></i> Reviews</a>
            <a href="withdrawals.php" class="nav-link"><i class="fas fa-wallet"></i> Withdrawals</a>
            <a href="store_settings.php" class="nav-link"><i class="fas fa-store-alt"></i> Store Settings</a>
        </nav>
        <div class="mt-auto"><a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <h2 class="fw-bold mb-4">Inventory History</h2>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-1">Products Restocked</div>
                        <h2 class="fw-bold m-0"><?php echo number_format($total_products_restocked); ?> <small class="text-muted fs-6">products</small></h2>
                    </div>
                    <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
        </div>

        <div class="logs-container">
            <?php if (empty($logs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x mb-3 text-muted" style="opacity: 0.3;"></i>
                    <p class="text-muted m-0">No stock history found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Date Added</th>
                                <th>Quantity Added</th>
                                <th>Current Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="../images/<?php echo $log['image']; ?>" class="prod-img">
                                        <div>
                                            <div class="fw-bold text-white"><?php echo htmlspecialchars($log['product_name']); ?></div>
                                            <small class="text-muted">ID: #<?php echo $log['product_id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-white"><?php echo date('M d, Y', strtotime($log['date_added'])); ?></div>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($log['date_added'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge-added"><i class="fas fa-plus me-1"></i> <?php echo $log['quantity_added']; ?></span>
                                </td>
                                <td>
                                    <span class="text-white-50"><?php echo $log['current_stock']; ?> units</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        
                        <tfoot>
                            <tr>
                                <td colspan="2" class="text-end text-white text-uppercase small fw-bold">Total Restocked:</td>
                                <td>
                                    <span class="text-success fw-bold fs-5">+ <?php echo number_format($total_units_added); ?></span>
                                    <small class="text-muted">units</small>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                        
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>