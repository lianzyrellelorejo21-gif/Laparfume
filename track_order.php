<?php
session_start();
require_once 'config/database.php';

// Check login status for Navbar
$is_logged_in = isset($_SESSION['user_id']);

$order_status = '';
$order_id = '';
$error = '';

// Handle Tracking Logic
if (isset($_GET['order_id'])) {
    $order_id = trim($_GET['order_id']);
    
    if (!empty($order_id)) {
        try {
            $stmt = $pdo->prepare("SELECT status, order_date, total_amount FROM orders WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $order_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($order_data) {
                $order_status = $order_data['status'];
            } else {
                $error = "Order #$order_id not found. Please check the ID.";
            }
        } catch (Exception $e) {
            $error = "System error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order - LaParfume</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    <link rel="stylesheet" href="assets/home.css">
    
    <style>
        /* Glassmorphism Card */
        .glass-panel {
            background: rgba(22, 22, 22, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-control {
            background-color: #2b2b2b;
            border: 1px solid #444;
            color: #fff;
            padding: 12px;
        }
        .form-control:focus {
            background-color: #333;
            border-color: #1dd1a1;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(29, 209, 161, 0.2);
        }
        
        .progress {
            background-color: #333;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-bar {
            background-color: #1dd1a1; /* Teal Green */
            font-weight: bold;
            color: black;
        }
    </style>
</head>
<body>
    
    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <nav class="navbar navbar-expand-lg navbar-dark mb-5">
        <div class="container">
            <a class="navbar-brand" href="index.php" class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Parfume</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>
                <div class="nav-icons text-white d-flex align-items-center gap-3">
                    <a href="wishlist.php" class="icon-btn"><i class="far fa-heart"></i></a>
                    <a href="customer/cart.php" class="icon-btn"><i class="fas fa-shopping-cart"></i></a>
                    <?php if ($is_logged_in): ?>
                        <a href="customer/account.php" class="icon-btn"><i class="far fa-user"></i></a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-sm btn-outline-light px-3 rounded-pill">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5 position-relative" style="z-index: 10;">
        <div class="text-center mb-5">
            <h1 class="text-white fw-bold">Track Your Order</h1>
            <p class="text-muted">Enter your Order ID to check the current status</p>
        </div>

        <div class="glass-panel">
            <form method="GET" action="" class="d-flex gap-2 mb-4">
                <input type="text" name="order_id" class="form-control" placeholder="e.g. 10" value="<?php echo htmlspecialchars($order_id); ?>" required>
                <button type="submit" class="btn btn-success fw-bold" style="background-color: #1dd1a1; border: none; color: black;">Track</button>
            </form>

            <?php if ($error): ?>
                <div class="alert alert-danger bg-dark border-danger text-danger text-center">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($order_status): ?>
                <div class="text-center">
                    <h5 class="text-white mb-3">Order #<?php echo htmlspecialchars($order_id); ?></h5>
                    <h2 class="fw-bold mb-4" style="color: #1dd1a1; text-transform: uppercase;">
                        <?php echo $order_status; ?>
                    </h2>

                    <div class="progress mb-2" style="height: 25px;">
                        <?php 
                            $width = '5%';
                            if($order_status == 'Pending') $width = '25%';
                            if($order_status == 'Processing') $width = '50%';
                            if($order_status == 'Shipped') $width = '75%';
                            if($order_status == 'Delivered') $width = '100%';
                        ?>
                        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: <?php echo $width; ?>">
                            <?php echo $width; ?>
                        </div>
                    </div>
                    <small class="text-muted">Order Progress</small>
                    
                    <div class="mt-4 pt-3 border-top border-secondary">
                        <div class="d-flex justify-content-between text-white-50 small">
                            <span>Date: <?php echo date('M d, Y', strtotime($order_data['order_date'])); ?></span>
                            <span>Total: ₱<?php echo number_format($order_data['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="mt-auto py-4 text-center border-top border-secondary position-relative z-1">
        <div class="container">
            <p class="mb-0 text-muted">&copy; ITP - 7 LaParfume System.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>