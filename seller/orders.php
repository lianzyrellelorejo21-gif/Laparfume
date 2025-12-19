<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// --- HANDLE STATUS UPDATE & NOTIFICATIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    // 1. Fetch current status AND customer_id (Needed for notification)
    $checkStmt = $pdo->prepare("SELECT status, customer_id FROM orders WHERE order_id = ?");
    $checkStmt->execute([$order_id]);
    $orderData = $checkStmt->fetch(PDO::FETCH_ASSOC);

    $currentStatus = $orderData['status'];
    $customer_id   = $orderData['customer_id'];

    // Prevent updates if already Cancelled or Delivered
    if ($currentStatus !== 'Cancelled' && $currentStatus !== 'Delivered') {
        
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        
        if ($stmt->execute([$new_status, $order_id])) {
            
            // 2. --- INSERT NOTIFICATION FOR CUSTOMER ---
            $notif_title = "Order Update";
            $notif_msg   = "Your order #$order_id status has been updated to: $new_status";
            
            try {
                $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $notifStmt->execute([$customer_id, $notif_title, $notif_msg]);
            } catch (Exception $e) {
                // Determine if we should handle error, for now we silently fail so flow continues
            }
            // ------------------------------------------
        }
    }
}

// --- FETCH SELLER'S ORDERS ---
$sql = "SELECT DISTINCT o.order_id, o.order_date, o.status, c.full_name, c.address, c.phone, oi.subtotal, p.product_name, p.image, oi.quantity
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        JOIN products p ON oi.product_id = p.product_id
        JOIN customers c ON o.customer_id = c.customer_id
        WHERE oi.seller_id = ?
        ORDER BY o.order_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$seller_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders - Seller Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login.css">
    
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; overflow-x: hidden; }
        .sidebar { width: 260px; background-color: #111; border-right: 1px solid #333; position: fixed; top: 0; left: 0; height: 100vh; padding: 20px; display: flex; flex-direction: column; z-index: 100; }
        .main-content { margin-left: 260px; padding: 30px; min-height: 100vh; }
        .nav-link { color: #aaa; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background-color: #1dd1a1; color: #000; font-weight: 600; }
        .sidebar .mt-auto { margin-top: auto !important; }
        
        .order-card { background: #161616; border: 1px solid #333; border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
        .order-header { background: #222; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; }
        .order-body { padding: 20px; }
        
        .badge-status { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .bg-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid #ffc107; }
        .bg-shipped { background: rgba(52, 152, 219, 0.2); color: #3498db; border: 1px solid #3498db; }
        .bg-delivered { background: rgba(46, 204, 113, 0.2); color: #2ecc71; border: 1px solid #2ecc71; }
        .bg-cancelled { background: rgba(220, 53, 69, 0.2); color: #dc3545; border: 1px solid #dc3545; }
        
        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -200px; left: -200px; } .blob-2 { bottom: -200px; right: -200px; }

        .form-select option { background-color: #222; color: #fff; }
        .form-select:disabled { background-color: #333; color: #aaa; border-color: #444; }
    </style>
</head>
<body>
    <div class="glow-blob blob-1"></div><div class="glow-blob blob-2"></div>

   <div class="sidebar">
        <div class="mb-5 px-2"><h3 class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Seller</span></h3></div>
        <nav class="nav flex-column">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="add_product.php" class="nav-link"><i class="fas fa-plus-circle"></i> Add Product</a>
            <a href="my_products.php" class="nav-link"><i class="fas fa-box"></i> My Products</a>
            <a href="stock_logs.php" class="nav-link"><i class="fas fa-history"></i> Stock Logs</a>
            <a href="orders.php" class="nav-link active"><i class="fas fa-shopping-bag"></i> Orders</a>
            <a href="my_reviews.php" class="nav-link"><i class="fas fa-star"></i> Reviews</a>
            <a href="withdrawals.php" class="nav-link"><i class="fas fa-wallet"></i> Withdrawals</a>
            <a href="store_settings.php" class="nav-link"><i class="fas fa-store-alt"></i> Store Settings</a>
        </nav>
        <div class="mt-auto"><a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <h2 class="fw-bold mb-4">Manage Orders</h2>

        <?php if (empty($orders)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-box-open fa-3x mb-3"></i>
                <p>No orders found yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <?php 
                // LOCK the order if it is Cancelled OR Delivered
                $is_locked = ($order['status'] === 'Cancelled' || $order['status'] === 'Delivered'); 
            ?>
            
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <span class="text-muted small">Order ID:</span> 
                        <span class="text-white fw-bold me-3">#<?php echo $order['order_id']; ?></span>
                        <span class="text-muted small"><i class="far fa-calendar me-1"></i> <?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                    </div>
                    <div>
                        <?php 
                            $statusClass = 'bg-secondary';
                            if ($order['status'] == 'Pending') $statusClass = 'bg-pending';
                            if ($order['status'] == 'Shipped') $statusClass = 'bg-shipped';
                            if ($order['status'] == 'Delivered') $statusClass = 'bg-delivered';
                            if ($order['status'] == 'Cancelled') $statusClass = 'bg-cancelled';
                        ?>
                        <span class="badge-status <?php echo $statusClass; ?>"><?php echo $order['status']; ?></span>
                    </div>
                </div>
                
                <div class="order-body">
                    <div class="row align-items-center">
                        <div class="col-md-5 d-flex align-items-center">
                            <img src="../images/<?php echo $order['image']; ?>" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; margin-right: 15px;">
                            <div>
                                <h6 class="mb-1 text-white"><?php echo htmlspecialchars($order['product_name']); ?></h6>
                                <small class="text-teal-400">Qty: <?php echo $order['quantity']; ?> x ₱<?php echo number_format($order['subtotal']/$order['quantity'], 2); ?></small>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <small class="text-muted d-block">Customer</small>
                            <span class="text-white"><?php echo htmlspecialchars($order['full_name']); ?></span>
                            <div class="small text-muted"><?php echo htmlspecialchars($order['address']); ?></div>
                        </div>

                        <div class="col-md-4 text-end">
                            <div class="d-flex justify-content-end align-items-center gap-2">
                                
                                <?php if ($order['status'] === 'Delivered'): ?>
                                    <a href="print_invoice.php?order_id=<?php echo $order['order_id']; ?>" target="_blank" class="btn btn-sm btn-outline-light" title="Print Invoice">
                                        <i class="fas fa-print"></i>
                                    </a>
                                <?php endif; ?>

                                <form action="orders.php" method="POST">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                    <div class="input-group input-group-sm">
                                        <select name="status" class="form-select bg-dark text-white border-secondary" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                            <option value="Pending" <?php if($order['status']=='Pending') echo 'selected'; ?>>Pending</option>
                                            <option value="Processing" <?php if($order['status']=='Processing') echo 'selected'; ?>>Processing</option>
                                            <option value="Shipped" <?php if($order['status']=='Shipped') echo 'selected'; ?>>Shipped</option>
                                            <option value="Delivered" <?php if($order['status']=='Delivered') echo 'selected'; ?>>Delivered</option>
                                            <option value="Cancelled" <?php if($order['status']=='Cancelled') echo 'selected'; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" class="btn <?php echo $is_locked ? 'btn-secondary' : 'btn-success'; ?>" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                            Update
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>