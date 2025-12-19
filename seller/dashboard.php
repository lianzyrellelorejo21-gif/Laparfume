<?php
session_start();
require_once '../config/database.php';

// 1. Security Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];
$seller_name = $_SESSION['user_name'];

// --- REVENUE CALCULATION (FIXED: Only Delivered Orders) ---
try {
    // 1. Total Earnings from Sales (Status = Delivered)
    $stmt = $pdo->prepare("
        SELECT SUM(oi.subtotal) 
        FROM order_items oi 
        JOIN orders o ON oi.order_id = o.order_id 
        WHERE oi.seller_id = ? AND o.status = 'Delivered'
    ");
    $stmt->execute([$seller_id]);
    $earnings = $stmt->fetchColumn() ?: 0.00;

    // 2. Total Withdrawn (Approved only)
    $stmt_w = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE seller_id = ? AND status = 'Approved'");
    $stmt_w->execute([$seller_id]);
    $withdrawn = $stmt_w->fetchColumn() ?: 0.00;

    // 3. Net Revenue (Available Balance)
    $total_revenue = $earnings - $withdrawn;

} catch (Exception $e) {
    $total_revenue = 0.00;
}

// --- NEW: COUNT PENDING ORDERS FOR ALERT ---
try {
    $stmt_pending = $pdo->prepare("
        SELECT COUNT(DISTINCT o.order_id) 
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE oi.seller_id = ? AND o.status = 'Pending'
    ");
    $stmt_pending->execute([$seller_id]);
    $pending_count = $stmt_pending->fetchColumn();
} catch (Exception $e) { $pending_count = 0; }

// --- CHART DATA (Last 7 Days - Only Delivered) ---
try {
    $chart_sql = "
        SELECT DATE(o.order_date) as sale_date, SUM(oi.subtotal) as daily_total
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.seller_id = ? AND o.status = 'Delivered'
        GROUP BY DATE(o.order_date)
        ORDER BY sale_date ASC
        LIMIT 7
    ";
    $chart_stmt = $pdo->prepare($chart_sql);
    $chart_stmt->execute([$seller_id]);
    $sales_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);

    $dates = []; $amounts = [];
    foreach ($sales_data as $data) {
        $dates[] = date('M d', strtotime($data['sale_date']));
        $amounts[] = $data['daily_total'];
    }
} catch (Exception $e) { $dates = []; $amounts = []; }

// Fetch Stats (Total Products & Total Delivered Orders)
$total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE seller_id = $seller_id")->fetchColumn();

// FIX: Only count Delivered orders for "Total Orders" stat
$total_orders = $pdo->query("
    SELECT COUNT(*) 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.order_id 
    WHERE oi.seller_id = $seller_id AND o.status = 'Delivered'
")->fetchColumn();

// Fetch Recent Products (Summary Only)
$stmt = $pdo->prepare("SELECT * FROM products WHERE seller_id = ? ORDER BY date_added DESC LIMIT 5");
$stmt->execute([$seller_id]);
$my_products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Seller Dashboard - LaParfume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="../assets/login.css">
    
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; overflow-x: hidden; min-height: 100vh; }
        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -200px; left: -200px; } .blob-2 { bottom: -200px; right: -200px; }
        .sidebar { width: 260px; background: rgba(22, 22, 22, 0.85); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.1); position: fixed; height: 100vh; top: 0; left: 0; padding: 20px; z-index: 100; display: flex; flex-direction: column; }
        .main-content { margin-left: 260px; padding: 30px; }
        .nav-link { color: #aaa; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background-color: #1dd1a1; color: #000; font-weight: 600; }
        .sidebar .mt-auto { margin-top: auto !important; }
        
        .stat-card { 
            background: rgba(22, 22, 22, 0.6); 
            backdrop-filter: blur(5px); 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            border-radius: 12px; 
            padding: 25px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            transition: transform 0.2s, border-color 0.2s; 
        }
        
        /* Hover Effect for Cards */
        .card-link { text-decoration: none; display: block; }
        .card-link:hover .stat-card {
            transform: translateY(-5px);
            border-color: #1dd1a1;
            box-shadow: 0 10px 20px rgba(29, 209, 161, 0.1);
        }

        .stat-icon { width: 50px; height: 50px; border-radius: 10px; background: rgba(29, 209, 161, 0.1); color: #1dd1a1; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .table-custom { background: rgba(22, 22, 22, 0.6); backdrop-filter: blur(5px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; overflow: hidden; width: 100%; }
        .table-custom th { background: rgba(34, 34, 34, 0.9); color: #fff; border: none; padding: 15px; }
        .table-custom td { background: transparent; color: #ccc; border-bottom: 1px solid rgba(255, 255, 255, 0.1); padding: 15px; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="glow-blob blob-1"></div><div class="glow-blob blob-2"></div>

    <div class="sidebar">
        <div class="mb-5 px-2"><h3 class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Seller</span></h3></div>
        <nav class="nav flex-column">
            <a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="add_product.php" class="nav-link"><i class="fas fa-plus-circle"></i> Add Product</a>
            <a href="my_products.php" class="nav-link"><i class="fas fa-box"></i> My Products</a>
            <a href="stock_logs.php" class="nav-link"><i class="fas fa-history"></i> Stock Logs</a>
            <a href="orders.php" class="nav-link d-flex justify-content-between align-items-center">
                <span><i class="fas fa-shopping-bag me-2"></i> Orders</span>
                <?php if($pending_count > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="my_reviews.php" class="nav-link"><i class="fas fa-star"></i> Reviews</a>
            <a href="withdrawals.php" class="nav-link"><i class="fas fa-wallet"></i> Withdrawals</a>
            <a href="store_settings.php" class="nav-link"><i class="fas fa-store-alt"></i> Store Settings</a>
        </nav>
        <div class="mt-auto"><a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div><h2 class="fw-bold text-white">Dashboard</h2><p class="text-muted">Welcome back, <?php echo htmlspecialchars($seller_name); ?></p></div>
            <a href="add_product.php" class="btn btn-success fw-bold" style="background-color: #1dd1a1; border: none; color: black; padding: 10px 25px;"><i class="fas fa-plus me-2"></i> Add New Product</a>
        </div>

        <?php if($pending_count > 0): ?>
        <div class="alert alert-warning bg-dark border-warning text-warning d-flex justify-content-between align-items-center mb-4">
            <div><i class="fas fa-bell me-2"></i> You have <strong><?php echo $pending_count; ?></strong> new order(s) to process.</div>
            <a href="orders.php" class="btn btn-sm btn-warning text-dark fw-bold">View Orders</a>
        </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <a href="my_products.php" class="card-link">
                    <div class="stat-card">
                        <div>
                            <h6 class="text-muted mb-1">Total Products</h6>
                            <h3 class="text-white fw-bold m-0"><?php echo $total_products; ?></h3>
                        </div>
                        <div class="stat-icon"><i class="fas fa-box"></i></div>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="orders.php" class="card-link">
                    <div class="stat-card">
                        <div>
                            <h6 class="text-muted mb-1">Total Completed Orders</h6>
                            <h3 class="text-white fw-bold m-0"><?php echo $total_orders; ?></h3>
                        </div>
                        <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                    </div>
                </a>
            </div>

            <div class="col-md-4">
                <a href="withdrawals.php" class="card-link">
                    <div class="stat-card">
                        <div>
                            <h6 class="text-muted mb-1">Available Revenue</h6>
                            <h3 class="text-white fw-bold m-0">₱<?php echo number_format($total_revenue, 2); ?></h3>
                        </div>
                        <div class="stat-icon"><i class="fas fa-wallet"></i></div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-12">
                <div class="stat-card d-block">
                    <h5 class="text-white mb-3">Sales Overview (Delivered Orders)</h5>
                    <div style="height: 300px;"><canvas id="salesChart"></canvas></div>
                </div>
            </div>
        </div>

        <h4 class="text-white mb-3">Recent Inventory Summary</h4>
        <div class="table-responsive">
            <table class="table table-custom">
                <thead><tr><th>Image</th><th>Product Name</th><th>Price</th><th>Current Stock</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (count($my_products) > 0): ?>
                        <?php foreach ($my_products as $product): ?>
                        <tr>
                            <td><img src="<?php echo !empty($product['image']) ? '../images/' . $product['image'] : 'https://via.placeholder.com/40'; ?>" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;"></td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td>₱<?php echo number_format($product['price'], 2); ?></td>
                            <td>
                                <?php if($product['stock'] <= 5): ?>
                                    <span class="text-danger fw-bold"><?php echo $product['stock']; ?> (Low)</span>
                                <?php else: ?>
                                    <span class="text-success"><?php echo $product['stock']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($product['is_active'] == 1): ?>
                                    <span class="badge bg-success bg-opacity-25 text-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-25 text-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No products found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($dates); ?>,
                datasets: [{ label: 'Daily Sales (₱)', data: <?php echo json_encode($amounts); ?>, borderColor: '#1dd1a1', backgroundColor: 'rgba(29, 209, 161, 0.1)', borderWidth: 2, fill: true, tension: 0.4 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#fff' } } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.1)' }, ticks: { color: '#aaa' } }, x: { grid: { color: 'rgba(255,255,255,0.1)' }, ticks: { color: '#aaa' } } } }
        });
    </script>
</body>
</html>