<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$customer_id = $_SESSION['user_id'];
$message = '';

// 1. Remove Item Logic
if (isset($_GET['action']) && $_GET['action'] == 'remove' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND customer_id = ?");
    $stmt->execute([$_GET['id'], $customer_id]);
    $message = "Item removed from cart.";
}

// 2. Update Quantity Logic (Used by JavaScript fetch call)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_qty_ajax') {
    $cart_id = $_POST['cart_id'];
    $requested_qty = max(1, intval($_POST['quantity']));

    // --- FIX: Check Real Stock from Database First ---
    $stockStmt = $pdo->prepare("
        SELECT p.stock 
        FROM cart c 
        JOIN products p ON c.product_id = p.product_id 
        WHERE c.cart_id = ? AND c.customer_id = ?
    ");
    $stockStmt->execute([$cart_id, $customer_id]);
    $product = $stockStmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $real_stock = $product['stock'];

        // If requested quantity is more than stock, cap it at max stock
        if ($requested_qty > $real_stock) {
            $requested_qty = $real_stock; 
            // You could echo an error here, but silently capping is safer for UI sync
        }

        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ? AND customer_id = ?");
        $stmt->execute([$requested_qty, $cart_id, $customer_id]);
        
        // Return the actual approved quantity to JS (in case we capped it)
        echo json_encode(['status' => 'success', 'new_qty' => $requested_qty]); 
    }
    exit(); // Stop here for AJAX
}

// Fetch Cart - ADDED p.stock to query
$stmt = $pdo->prepare("
    SELECT c.cart_id, c.quantity, p.product_id, p.product_name, p.price, p.discount_price, p.image, p.stock
    FROM cart c JOIN products p ON c.product_id = p.product_id 
    WHERE c.customer_id = ?");
$stmt->execute([$customer_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- FETCH CUSTOMER IMAGE LOGIC (ADDED) ---
$user_profile_pic = "images/Profile.jpg"; // Default fallback

try {
    // Fetch the user's specific image filename
    $stmt_img = $pdo->prepare("SELECT profile_image FROM customers WHERE customer_id = ?");
    $stmt_img->execute([$customer_id]);
    $row = $stmt_img->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['profile_image'])) {
        $db_img = $row['profile_image']; 
        
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
    <title>My Cart - LaParfume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; }
        .cart-container { background: rgba(22, 22, 22, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; padding: 30px; }
        .table-cart th { color: #aaa; border: none; text-transform: uppercase; font-size: 0.85rem; padding: 15px; }
        .table-cart td { vertical-align: middle; padding: 15px; background: rgba(255, 255, 255, 0.03); border: none; }
        .table-cart tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table-cart tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }
        .product-img { width: 70px; height: 70px; object-fit: cover; border-radius: 10px; border: 1px solid #333; }
        
        /* QTY BUTTONS */
        .qty-box { display: flex; align-items: center; background: #111; border: 1px solid #333; border-radius: 5px; width: fit-content; margin: 0 auto; }
        .qty-btn { background: none; border: none; color: #fff; padding: 5px 10px; cursor: pointer; }
        .qty-btn:hover { color: #1dd1a1; }
        .qty-input { width: 40px; background: none; border: none; color: #fff; text-align: center; font-weight: bold; -moz-appearance: textfield; }
        .qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        
        .item-checkbox { width: 20px; height: 20px; accent-color: #1dd1a1; cursor: pointer; }
        .btn-remove { color: #ff4757; transition: 0.3s; }
        .btn-remove:hover { color: #ff6b81; transform: scale(1.1); }
        
        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -200px; left: -200px; } .blob-2 { bottom: -200px; right: -200px; }
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
                    <li class="nav-item"><a class="nav-link active" href="cart.php">Cart</a></li>
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

    <div class="container py-4 position-relative" style="z-index: 10;">
        <h2 class="text-center fw-bold mb-5 text-white">Your Shopping Cart</h2>

        <?php if ($message): ?><div class="alert alert-success bg-dark border-success text-success text-center mb-4"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

        <form action="checkout.php" method="POST" id="cartForm">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="cart-container">
                        <?php if (empty($cart_items)): ?>
                            <div class="text-center py-5"><i class="fas fa-shopping-basket fa-4x mb-3 text-secondary opacity-50"></i><h4 class="text-white">Your cart is currently empty</h4><a href="shop.php" class="btn btn-success mt-3" style="background:#1dd1a1; border:none; color:black;">Start Shopping</a></div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table-cart">
                                    <thead><tr><th width="5%"><input type="checkbox" id="selectAll" class="item-checkbox" onclick="toggleSelectAll()"></th><th>Product</th><th>Price</th><th class="text-center">Qty</th><th class="text-end">Total</th><th></th></tr></thead>
                                    <tbody>
                                        <?php foreach ($cart_items as $item): ?>
                                        <?php 
                                            $final_price = ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']) ? $item['discount_price'] : $item['price'];
                                            $line_total = $final_price * $item['quantity'];
                                            // Ensure we don't display more than stock if DB was already wrong
                                            $display_qty = ($item['quantity'] > $item['stock']) ? $item['stock'] : $item['quantity'];
                                        ?>
                                        <tr id="row-<?php echo $item['cart_id']; ?>">
                                            <td>
                                                <input type="checkbox" name="selected_items[]" value="<?php echo $item['cart_id']; ?>" 
                                                       class="item-checkbox product-checkbox" 
                                                       data-base-price="<?php echo $final_price; ?>" 
                                                       onclick="updateTotal()">
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="images/<?php echo htmlspecialchars($item['image']); ?>" class="product-img me-3">
                                                    <div>
                                                        <div class="fw-bold text-white"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                        <?php if($final_price < $item['price']): ?><span class="badge bg-danger" style="font-size: 0.6rem;">Flash Sale!</span><?php endif; ?>
                                                        
                                                        <?php if($item['stock'] <= 0): ?>
                                                            <div class="text-danger small">Out of Stock</div>
                                                        <?php elseif($item['stock'] < 10): ?>
                                                            <div class="text-warning small">Only <?php echo $item['stock']; ?> left!</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php if($final_price < $item['price']): ?><div class="text-danger fw-bold">₱<?php echo number_format($final_price, 2); ?></div><small class="text-muted text-decoration-line-through">₱<?php echo number_format($item['price'], 2); ?></small><?php else: ?><div class="text-white">₱<?php echo number_format($final_price, 2); ?></div><?php endif; ?></td>
                                            <td>
                                                <div class="qty-box">
                                                    <button type="button" class="qty-btn" onclick="changeQty(<?php echo $item['cart_id']; ?>, -1)">-</button>
                                                    
                                                    <input type="number" id="qty-<?php echo $item['cart_id']; ?>" 
                                                           value="<?php echo $display_qty; ?>" 
                                                           class="qty-input" 
                                                           data-max-stock="<?php echo $item['stock']; ?>" 
                                                           readonly>

                                                    <button type="button" class="qty-btn" onclick="changeQty(<?php echo $item['cart_id']; ?>, 1)">+</button>
                                                </div>
                                            </td>
                                            <td class="text-end text-teal-400 fw-bold" id="total-<?php echo $item['cart_id']; ?>">₱<?php echo number_format($line_total, 2); ?></td>
                                            <td class="text-center"><a href="cart.php?action=remove&id=<?php echo $item['cart_id']; ?>" class="btn-remove" onclick="return confirm('Remove item?');"><i class="fas fa-trash-alt"></i></a></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($cart_items)): ?>
                <div class="col-lg-4">
                    <div class="cart-container h-100">
                        <h4 class="text-white fw-bold mb-4">Order Summary</h4>
                        <div class="d-flex justify-content-between mb-2"><span class="text-white-50">Selected Items (<span id="selectedCount">0</span>)</span><span id="displaySubtotal">₱0.00</span></div>
                        <div class="d-flex justify-content-between mb-2"><span class="text-white-50">Shipping</span><span>₱50.00</span></div>
                        <div class="summary-total"><span>Total</span><span class="text-teal-400" id="displayTotal">₱0.00</span></div>
                        <button type="submit" class="btn btn-success w-100 mt-4 py-3 fw-bold text-uppercase" style="background:#1dd1a1; border:none; color:black;">Proceed to Checkout <i class="fas fa-arrow-right ms-2"></i></button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function changeQty(cartId, change) {
            let input = document.getElementById('qty-' + cartId);
            let currentQty = parseInt(input.value);
            
            // Get Stock Limit from HTML
            let maxStock = parseInt(input.getAttribute('data-max-stock'));
            
            let newQty = currentQty + change;

            // Check Min Quantity
            if (newQty < 1) return;

            // Check Max Quantity (Stock Limit)
            if (change > 0 && newQty > maxStock) {
                alert("Sorry, only " + maxStock + " item(s) available in stock.");
                return; // Stop the function
            }

            input.value = newQty;

            // Update individual row total visually
            let checkbox = document.querySelector(`input[value="${cartId}"]`);
            let price = parseFloat(checkbox.getAttribute('data-base-price'));
            let newTotal = price * newQty;
            document.getElementById('total-' + cartId).innerText = '₱' + newTotal.toLocaleString('en-US', {minimumFractionDigits: 2});

            // Update Grand Total
            updateTotal();

            // Send AJAX request to update DB
            let formData = new FormData();
            formData.append('action', 'update_qty_ajax');
            formData.append('cart_id', cartId);
            formData.append('quantity', newQty);
            
            fetch('cart.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    // Double check if backend capped the quantity
                    if (parseInt(data.new_qty) !== newQty) {
                       input.value = data.new_qty; // correct the input visually
                       alert("Quantity adjusted to maximum available stock.");
                    }
                }
            });
        }

        function updateTotal() {
            let checkboxes = document.querySelectorAll('.product-checkbox');
            let subtotal = 0;
            let count = 0;
            let shipping = 50;

            checkboxes.forEach(box => {
                if (box.checked) {
                    let cartId = box.value;
                    let qty = parseInt(document.getElementById('qty-' + cartId).value);
                    let price = parseFloat(box.getAttribute('data-base-price'));
                    subtotal += (price * qty);
                    count++;
                }
            });

            let total = (count > 0) ? subtotal + shipping : 0;

            document.getElementById('selectedCount').innerText = count;
            document.getElementById('displaySubtotal').innerText = '₱' + subtotal.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('displayTotal').innerText = '₱' + total.toLocaleString('en-US', {minimumFractionDigits: 2});
        }

        function toggleSelectAll() {
            let mainBox = document.getElementById('selectAll');
            let checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(box => box.checked = mainBox.checked);
            updateTotal();
        }
    </script>
</body>
</html>