<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If AJAX request, return JSON error
    if (isset($_POST['add_to_cart'])) {
        echo json_encode(['status' => 'error', 'message' => 'Please login first.']);
        exit();
    }
    // If standard request, redirect to login
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = $_SESSION['user_id']; 
    $product_id = $_POST['product_id'] ?? null;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    // Check if this is an AJAX request (sent from JavaScript)
    $is_ajax = isset($_POST['add_to_cart']);

    if ($product_id) {
        // --- 1. CHECK STOCK LEVEL FROM DATABASE ---
        // Using $pdo as your connection variable based on your snippet
        $stmt = $pdo->prepare("SELECT product_name, stock FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        // Safety check: Does product exist?
        if (!$product) {
            if ($is_ajax) { 
                echo json_encode(['status' => 'error', 'message' => 'Product not found.']); 
                exit; 
            }
            header('Location: index.php'); 
            exit;
        }

        // --- STOCK CHECK 1: Is the requested quantity available? ---
        if ($quantity > $product['stock']) {
            $msg = "Sorry, only " . $product['stock'] . " items of " . $product['product_name'] . " left in stock.";
            
            if ($is_ajax) { 
                // Return JSON for JS popup
                echo json_encode(['status' => 'error', 'message' => $msg]); 
                exit; 
            }
            // Return Script for Browser Popup
            echo "<script>alert('$msg'); window.history.back();</script>"; 
            exit;
        }

        // --- CASE 1: BUY NOW BUTTON (Direct to Checkout) ---
        if (isset($_POST['buy_now'])) {
            $_SESSION['buy_now_item'] = [
                'product_id' => $product_id,
                'quantity' => $quantity
            ];
            header('Location: checkout.php');
            exit();
        }

        // --- CASE 2: ADD TO CART BUTTON (Save to DB) ---
        
        // Check if product is ALREADY in the cart to validate CUMULATIVE stock
        // (e.g. User has 5 in cart, stock is 10. User tries to add 6 more. Total 11 > 10. Error.)
        $stmt = $pdo->prepare("SELECT cart_id, quantity FROM cart WHERE customer_id = ? AND product_id = ?");
        $stmt->execute([$customer_id, $product_id]);
        $existing_item = $stmt->fetch();

        $current_cart_qty = $existing_item ? $existing_item['quantity'] : 0;
        $total_qty = $current_cart_qty + $quantity;

        // --- STOCK CHECK 2: Does Total (Cart + New) exceed Stock? ---
        if ($total_qty > $product['stock']) {
            $remaining_allowance = $product['stock'] - $current_cart_qty;
            $msg = "You already have $current_cart_qty in your cart. You can only add $remaining_allowance more.";
            
            if ($is_ajax) { 
                echo json_encode(['status' => 'error', 'message' => $msg]); 
                exit; 
            }
            echo "<script>alert('$msg'); window.history.back();</script>"; 
            exit;
        }

        // Update existing cart item OR Insert new item
        if ($existing_item) {
            $update_stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
            $update_stmt->execute([$total_qty, $existing_item['cart_id']]);
        } else {
            $insert_stmt = $pdo->prepare("INSERT INTO cart (customer_id, product_id, quantity) VALUES (?, ?, ?)");
            $insert_stmt->execute([$customer_id, $product_id, $quantity]);
        }

        // --- SUCCESS RESPONSE ---
        if ($is_ajax) {
            echo json_encode(['status' => 'success', 'message' => 'Successfully added to cart!']);
            exit;
        }
    }
    
    // Redirect back to the previous page (Fallback for non-JS)
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'cart.php'));
    exit();
}
header('Location: index.php');
?>