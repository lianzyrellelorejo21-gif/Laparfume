<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) { exit("Invalid Request"); }
$seller_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'];

// Fetch Order & Customer Info
$stmt = $pdo->prepare("SELECT o.*, c.full_name, c.email, c.phone, c.address FROM orders o JOIN customers c ON o.customer_id = c.customer_id WHERE o.order_id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Items for THIS seller only
$stmtItems = $pdo->prepare("SELECT oi.*, p.product_name, p.image FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = ? AND oi.seller_id = ?");
$stmtItems->execute([$order_id, $seller_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$seller_total = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #<?php echo $order_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body { background-color: #fff; color: #000; font-family: 'Poppins', sans-serif; padding: 40px; }
        
        .invoice-card { 
            background: #fff; 
            max-width: 850px; 
            margin: 0 auto; 
            border: 1px solid #ddd; /* Subtle border for screen view */
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .table-custom { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table-custom th { 
            background-color: #f8f9fa; 
            color: #555; 
            text-transform: uppercase; 
            font-size: 0.85rem; 
            padding: 12px; 
            border-bottom: 2px solid #ddd; 
        }
        .table-custom td { 
            padding: 15px 12px; 
            border-bottom: 1px solid #eee; 
            vertical-align: middle; 
        }
        
        .status-badge { 
            display: inline-block; 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 0.8rem; 
            font-weight: 600; 
            text-transform: uppercase; 
            border: 1px solid #000; 
        }

        /* PRINT STYLES */
        @media print {
            body { padding: 0; background: #fff; }
            .invoice-card { box-shadow: none; border: none; padding: 0; margin: 0; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
            .table-custom th { background-color: #eee !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="invoice-card">
        
        <div class="text-end mb-4 no-print">
            <button onclick="window.print()" class="btn btn-dark"><i class="fas fa-print me-2"></i> Print Invoice</button>
        </div>

        <div class="d-flex justify-content-between border-bottom pb-4 mb-4">
            <div>
                <h2 class="fw-bold m-0" style="color: #1dd1a1;">LA<span style="color: #000;">Parfume</span></h2>
                <p class="text-muted small mb-0">Seller: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></p>
            </div>
            <div class="text-end">
                <h4 class="fw-bold">INVOICE</h4>
                <div class="text-muted small">Order ID: #<?php echo $order_id; ?></div>
                <div class="text-muted small">Date: <?php echo date('M d, Y', strtotime($order['order_date'])); ?></div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-6">
                <h6 class="text-uppercase text-muted small fw-bold mb-2">Bill To</h6>
                <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($order['full_name']); ?></h5>
                <div class="text-muted small"><?php echo htmlspecialchars($order['email']); ?></div>
                <div class="text-muted small"><?php echo htmlspecialchars($order['phone']); ?></div>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-uppercase text-muted small fw-bold mb-2">Ship To</h6>
                <div class="text-dark small" style="white-space: pre-line;"><?php echo htmlspecialchars($order['address']); ?></div>
            </div>
        </div>

        <table class="table-custom">
            <thead>
                <tr>
                    <th class="text-start">Item Description</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $item): 
                    $line_total = $item['price'] * $item['quantity'];
                    $seller_total += $line_total;
                ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    </td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-end">₱<?php echo number_format($item['price'], 2); ?></td>
                    <td class="text-end fw-bold">₱<?php echo number_format($line_total, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="row mt-4">
            <div class="col-6">
                <p class="text-muted small mt-2">
                    This invoice only includes items sold by <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong>.
                </p>
            </div>
            <div class="col-6">
                <div class="d-flex justify-content-end align-items-center pt-2">
                    <div class="text-end me-4">
                        <div class="fw-bold h5 mb-0">Total Due:</div>
                    </div>
                    <div class="h4 fw-bold m-0" style="color: #1dd1a1;">₱<?php echo number_format($seller_total, 2); ?></div>
                </div>
            </div>
        </div>

        <div class="mt-5 pt-4 border-top text-center">
            <p class="text-muted small mb-1">Thank you for your business!</p>
            <div class="small fw-bold">LaParfume</div>
        </div>

    </div>

</body>
</html>