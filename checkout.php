<?php
session_start();
require_once 'config/database.php';

// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// At the top of checkout.php
if (isset($_POST['buy_now']) && $_POST['buy_now'] == '1') {
    // This is a direct "Buy Now" purchase
    // Create a temporary order array from POST data
    $direct_purchase = [
        'product_id' => $_POST['product_id'],
        'quantity' => $_POST['quantity'],
        'selected_volume' => $_POST['selected_volume'] ?? null
    ];
    
    // Use $direct_purchase instead of cart items
    // Don't read from $_SESSION['cart']
} else {
    // Normal checkout from cart
    // Use $_SESSION['cart'] as usual
}

$customer_id = $_SESSION['user_id'];

// 2. Fetch Customer Info
$stmt = $pdo->prepare("SELECT * FROM customers WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// --- 3. DETERMINE SOURCE (Order of Priority: Cart Selection > Buy Now Session > Fallback) ---
$cart_items = [];

// PRIORITY 1: CHECKOUT FROM CART (Selected Items)
if (isset($_POST['selected_items']) && !empty($_POST['selected_items'])) {
    
    // Clear any stuck "Buy Now" session so it doesn't interfere later
    unset($_SESSION['buy_now_item']);
    
    $selected_ids = $_POST['selected_items'];
    
    // Create placeholder string (?,?,?)
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    
    $sql = "SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.price, p.discount_price, p.image, p.seller_id 
            FROM cart c 
            JOIN products p ON c.product_id = p.product_id 
            WHERE c.customer_id = ? AND c.cart_id IN ($placeholders)";
            
    $params = array_merge([$customer_id], $selected_ids);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
// PRIORITY 2: BUY NOW (Single Item)
elseif (isset($_SESSION['buy_now_item'])) {
    $pid = $_SESSION['buy_now_item']['product_id'];
    $qty = $_SESSION['buy_now_item']['quantity'];
    
    $stmt = $pdo->prepare("SELECT product_id, product_name, price, discount_price, image, seller_id FROM products WHERE product_id = ?");
    $stmt->execute([$pid]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $product['quantity'] = $qty;
        $cart_items[] = $product;
    }
} 
// PRIORITY 3: FALLBACK (Fetch All Cart Items - Legacy Support)
else {
    $stmt = $pdo->prepare("
        SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.price, p.discount_price, p.image, p.seller_id 
        FROM cart c 
        JOIN products p ON c.product_id = p.product_id 
        WHERE c.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If empty, redirect
if (empty($cart_items)) {
    header('Location: shop.php');
    exit();
}

// 4. Calculate Totals
$subtotal = 0;
foreach ($cart_items as &$item) {
    // Flash Sale Logic
    if ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']) {
        $final_price = $item['discount_price'];
    } else {
        $final_price = $item['price'];
    }
    $item['final_price_to_use'] = $final_price;
    $subtotal += ($final_price * $item['quantity']);
}
unset($item);

$shipping = 50.00;
$total = $subtotal + $shipping;

// --- FETCH CUSTOMER IMAGE LOGIC (ADDED) ---
$user_profile_pic = "images/Profile.jpg"; // Default fallback

try {
    // Note: We already fetched customer data above into $customer variable
    if ($customer && !empty($customer['profile_image'])) {
        $db_img = $customer['profile_image']; 
        
        // Check multiple folder possibilities
        $candidates = [
            "images/" . $db_img,            
            "customer/images/" . $db_img,   
            $db_img                         
        ];
        
        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $user_profile_pic = $path; // Found the custom image
                break;
            }
        }
    }
} catch (Exception $e) {
    // Ignore errors, keep default
}

// Final Safety Check
if (!file_exists($user_profile_pic)) {
    $user_profile_pic = "https://via.placeholder.com/150/1dd1a1/000000?text=Profile";
}
// ------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - LaParfume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    <style>
        .checkout-container { background-color: #161616; border-radius: 12px; padding: 30px; border: 1px solid rgba(255, 255, 255, 0.05); box-shadow: 0 20px 60px rgba(0, 0, 0, 0.7); }
        .form-label { color: #aaa; font-size: 0.9rem; }
        .form-control { background-color: #000; border: 1px solid #333; color: #fff; padding: 12px; }
        .form-control:focus { background-color: #000; border-color: #1dd1a1; color: #fff; box-shadow: none; }
        .order-summary-item { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; border-bottom: 1px solid #333; padding-bottom: 15px; }
        .payment-option { border: 1px solid #333; border-radius: 8px; padding: 15px; margin-bottom: 10px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: space-between; }
        .payment-option:hover, .payment-option.selected { border-color: #1dd1a1; background-color: rgba(29, 209, 161, 0.05); }
        .form-check-input:checked { background-color: #1dd1a1; border-color: #1dd1a1; }
    </style>
</head>
<body>
    <div class="glow-blob blob-1"></div><div class="glow-blob blob-2"></div>

    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php" class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Parfume</span></a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                </ul>
                <div class="nav-icons text-white d-flex gap-3 align-items-center">
                    <a href="wishlist.php" class="icon-btn"><i class="far fa-heart"></i></a>
                    <a href="cart.php" class="icon-btn text-teal-400"><i class="fas fa-shopping-cart"></i></a>
                    
                    <a href="customer/account.php" class="icon-btn d-flex align-items-center justify-content-center" title="Profile">
                        <img src="<?php echo htmlspecialchars($user_profile_pic); ?>?v=<?php echo time(); ?>" 
                             alt="Profile" 
                             style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid #1dd1a1;">
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="position: relative; z-index: 10;">
        <form action="process_checkout.php" method="POST">
            
            <?php if (isset($_POST['selected_items']) && !empty($_POST['selected_items'])): ?>
                <?php foreach($_POST['selected_items'] as $id): ?>
                    <input type="hidden" name="selected_items[]" value="<?php echo htmlspecialchars($id); ?>">
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="checkout-container">
                        <h4 class="text-white mb-4 fw-bold">Billing Details</h4>
                        <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($customer['full_name'] ?? ''); ?>"></div>
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="form-label">Email Address</label><input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>"></div>
                            <div class="col-md-6 mb-3"><label class="form-label">Phone Number</label><input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>"></div>
                        </div>
                        <div class="mb-3"><label class="form-label">Shipping Address</label><textarea name="address" class="form-control" rows="3" required><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea></div>
                        <div class="row">
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control" value="Oroquieta City" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="postal_code" class="form-control" value="7207" required>
                            </div>

                        </div>
                        <div class="mb-3"><label class="form-label">Order Notes (Optional)</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="checkout-container">
                        <h4 class="text-white mb-4 fw-bold">Your Order</h4>
                        <div class="mb-4">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="order-summary-item">
                                <div class="d-flex align-items-center">
                                    <img src="images/<?php echo htmlspecialchars($item['image']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; margin-right: 15px;">
                                    <div>
                                        <div class="text-white small"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <div class="text-white-50 small">Qty: <?php echo $item['quantity']; ?></div>
                                        <?php if($item['final_price_to_use'] < $item['price']): ?>
                                            <div class="text-danger small" style="font-size: 0.75rem;">Flash Sale Applied!</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-teal-400 fw-bold">₱<?php echo number_format($item['final_price_to_use'] * $item['quantity'], 2); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex justify-content-between mb-2"><span class="text-white-50">Subtotal</span><span class="text-white fw-bold">₱<?php echo number_format($subtotal, 2); ?></span></div>
                        <div class="d-flex justify-content-between mb-4 pb-3 border-bottom border-secondary"><span class="text-white-50">Shipping</span><span class="text-white fw-bold">₱<?php echo number_format($shipping, 2); ?></span></div>
                        <div class="d-flex justify-content-between mb-4"><span class="h5 text-white">Total</span><span class="h4 text-teal-400 fw-bold">₱<?php echo number_format($total, 2); ?></span></div>

                        <h5 class="text-white mb-3">Payment Method</h5>
                        <div class="payment-group">
                            <label class="payment-option"><div><input type="radio" name="payment_method" value="Cash on Delivery" class="form-check-input me-2" checked><span class="text-white">Cash on Delivery</span></div><i class="fas fa-money-bill-wave text-muted"></i></label>
                            <label class="payment-option"><div><input type="radio" name="payment_method" value="GCash" class="form-check-input me-2"><span class="text-white">GCash</span></div><i class="fas fa-mobile-alt text-muted"></i></label>
                            <label class="payment-option"><div><input type="radio" name="payment_method" value="Credit Card" class="form-check-input me-2"><span class="text-white">Credit Card</span></div><i class="far fa-credit-card text-muted"></i></label>
                        </div>

                        <button type="submit" name="place_order" class="btn btn-login w-100 mt-4 py-3 text-uppercase fw-bold" style="letter-spacing: 1px;">Place Order</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const paymentOptions = document.querySelectorAll('.payment-option');
        paymentOptions.forEach(option => {
            option.addEventListener('click', () => {
                paymentOptions.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                option.querySelector('input').checked = true;
            });
        });
        document.querySelector('input[checked]').closest('.payment-option').classList.add('selected');
    </script>
</body>
</html>