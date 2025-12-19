<?php
session_start();
require_once 'config/database.php';

// --- HELPER FUNCTION DEFINITION ---
if (!function_exists('logActivity')) {
    function logActivity($pdo, $type, $message) {
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_logs (log_type, message) VALUES (?, ?)");
            $stmt->execute([$type, $message]);
        } catch (Exception $e) { }
    }
}
// ----------------------------------

if (!isset($_SESSION['user_id']) || !isset($_POST['place_order'])) {
    header('Location: index.php');
    exit();
}

$customer_id = $_SESSION['user_id'];
$customer_name = $_SESSION['user_name'] ?? 'Customer';
$shipping_address = $_POST['address'] . ', ' . $_POST['city'] . ' ' . $_POST['postal_code'];
$contact_phone = $_POST['phone'];
$notes = $_POST['notes'];
$payment_method = $_POST['payment_method'];

try {
    $pdo->beginTransaction();

    // --- 1. FETCH ITEMS TO PROCESS ---
    $cart_items = [];
    $is_buy_now = isset($_SESSION['buy_now_item']); // Flag to check mode
    
    // Variable to store IDs for cleanup later
    $cart_ids_to_delete = []; 

    if ($is_buy_now) {
        // --- MODE A: BUY NOW ---
        $pid = $_SESSION['buy_now_item']['product_id'];
        $qty = $_SESSION['buy_now_item']['quantity'];
        $stmt = $pdo->prepare("SELECT product_id, product_name, price, discount_price, seller_id FROM products WHERE product_id = ?");
        $stmt->execute([$pid]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($product) {
            $product['quantity'] = $qty;
            $cart_items[] = $product;
        }
    } else {
        // --- MODE B: CART (Selected Items Only) ---
        $cart_ids = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];

        // Safety: If no items selected, redirect back
        if (empty($cart_ids)) {
            header('Location: cart.php'); exit();
        }

        // Store IDs for deletion step later
        $cart_ids_to_delete = $cart_ids;

        // Build Query for specific IDs
        $placeholders = implode(',', array_fill(0, count($cart_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT c.cart_id, c.product_id, c.quantity, p.product_name, p.price, p.discount_price, p.seller_id 
            FROM cart c JOIN products p ON c.product_id = p.product_id 
            WHERE c.customer_id = ? AND c.cart_id IN ($placeholders)
        ");
        
        // Merge Customer ID with the array of Cart IDs
        $params = array_merge([$customer_id], $cart_ids);
        $stmt->execute($params);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($cart_items)) throw new Exception("Order Items missing.");

    // --- 2. CALCULATE TOTAL ---
    $subtotal = 0;
    foreach ($cart_items as $key => $item) {
        $price = ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']) ? $item['discount_price'] : $item['price'];
        $cart_items[$key]['final_price'] = $price;
        $subtotal += ($price * $item['quantity']);
    }
    $total_amount = $subtotal + 50.00; // Fixed Shipping

    // --- 3. INSERT ORDER ---
    $stmt_order = $pdo->prepare("INSERT INTO orders (customer_id, total_amount, shipping_address, contact_phone, status, notes, order_date) VALUES (?, ?, ?, ?, 'Pending', ?, NOW())");
    $stmt_order->execute([$customer_id, $total_amount, $shipping_address, $contact_phone, $notes]);
    $order_id = $pdo->lastInsertId();

    // --- 4. INSERT ITEMS & UPDATE STOCK ---
    $stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, seller_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt_stock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ?");

    foreach ($cart_items as $item) {
        $line_total = $item['final_price'] * $item['quantity'];
        $stmt_item->execute([$order_id, $item['product_id'], $item['seller_id'], $item['quantity'], $item['final_price'], $line_total]);
        $stmt_stock->execute([$item['quantity'], $item['product_id']]);
    }

    // --- 5. INSERT PAYMENT ---
    $pdo->prepare("INSERT INTO payments (order_id, payment_method, payment_status, amount, payment_date) VALUES (?, ?, 'Pending', ?, NOW())")->execute([$order_id, $payment_method, $total_amount]);

    // --- 6. CLEANUP (CRITICAL FIX) ---
    if ($is_buy_now) {
        // If Buy Now: Just clear the session variable
        unset($_SESSION['buy_now_item']);
    } else {
        // If Cart: Delete ONLY the items that were selected and bought
        if (!empty($cart_ids_to_delete)) {
            $placeholders = implode(',', array_fill(0, count($cart_ids_to_delete), '?'));
            $sql_delete = "DELETE FROM cart WHERE customer_id = ? AND cart_id IN ($placeholders)";
            $params_delete = array_merge([$customer_id], $cart_ids_to_delete);
            $stmt_clear = $pdo->prepare($sql_delete);
            $stmt_clear->execute($params_delete);
        }
    }

    // Log Activity
    logActivity($pdo, 'Order', "New Order #$order_id placed by $customer_name ($" . number_format($total_amount, 2) . ")");

    $pdo->commit();
    header('Location: order_success.php?order_id=' . $order_id);
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>