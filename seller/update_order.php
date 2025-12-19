<?php
session_start();
require_once '../config/database.php';
require_once '../config/email_sender.php'; // <--- INCLUDE THE NEW MAILER

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php'); exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['order_id']) && isset($_POST['status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $seller_id = $_SESSION['user_id'];

    // 1. Fetch Customer Info
    $stmt = $pdo->prepare("
        SELECT o.customer_id, c.email, c.full_name 
        FROM orders o 
        JOIN order_items oi ON o.order_id = oi.order_id 
        JOIN customers c ON o.customer_id = c.customer_id
        WHERE oi.seller_id = ? AND o.order_id = ?
        LIMIT 1
    ");
    $stmt->execute([$seller_id, $order_id]);
    $order_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order_data) {
        $customer_id = $order_data['customer_id'];

        // 2. Update Order Status
        $updateStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        if ($updateStmt->execute([$status, $order_id])) {
            
            // 3. SEND EMAIL (USING PHPMAILER)
            $to = $order_data['email'];
            $customer_name = $order_data['full_name'];
            $subject = "Order #$order_id Update: $status";
            
            // Professional HTML Template
            $message = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 10px;'>
                <h2 style='color: #1dd1a1;'>LaParfume Update</h2>
                <p>Hi <strong>$customer_name</strong>,</p>
                <p>Your order <strong>#$order_id</strong> has been updated.</p>
                <div style='background: #f4f4f4; padding: 15px; text-align: center; border-radius: 5px; font-weight: bold; font-size: 18px;'>
                    New Status: <span style='color: #1dd1a1;'>$status</span>
                </div>
                <p>Please check your account for more details.</p>
                <hr>
                <small style='color: #888;'>Thank you for shopping with us!</small>
            </div>";

            // CALL THE FUNCTION
            sendEmail($to, $subject, $message);

            // 4. INSERT NOTIFICATION (Existing Logic)
            $notif_title = "Order Update";
            $notif_msg = "Your order #$order_id status has been updated to: $status";
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $notifStmt->execute([$customer_id, $notif_title, $notif_msg]);

            header("Location: orders.php?success=status_updated");
            exit();
        }
    }
}
header("Location: orders.php?error=update_failed");
exit();
?>