<?php
session_start();
require_once 'config/database.php';

// 1. Check Login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// 2. Add to Wishlist Logic
if (isset($_POST['product_id'])) {
    $customer_id = $_SESSION['user_id'];
    $product_id = $_POST['product_id'];

    // Check if already in wishlist to prevent duplicates
    $check = $pdo->prepare("SELECT wishlist_id FROM wishlist WHERE customer_id = ? AND product_id = ?");
    $check->execute([$customer_id, $product_id]);

    if (!$check->fetch()) {
        // Insert into database
        $stmt = $pdo->prepare("INSERT INTO wishlist (customer_id, product_id) VALUES (?, ?)");
        $stmt->execute([$customer_id, $product_id]);
    }
}

// 3. REDIRECT TO WISHLIST PAGE (As requested)
header('Location: wishlist.php');
exit();
?>