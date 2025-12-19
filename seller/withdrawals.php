<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php'); exit();
}

$seller_id = $_SESSION['user_id'];
$message = '';
$error = '';
$show_modal = false; // <--- 1. Initialize Modal Flag

// --- 1. CALCULATE FINANCES ---

// A. Total Lifetime Earnings (From Delivered Orders)
$stmt = $pdo->prepare("
    SELECT SUM(oi.subtotal) 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.order_id 
    WHERE oi.seller_id = ? AND o.status = 'Delivered'
");
$stmt->execute([$seller_id]);
$lifetime_earnings = $stmt->fetchColumn() ?: 0.00;

// B. Total Amount Already Withdrawn (Approved)
$stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE seller_id = ? AND status = 'Approved'");
$stmt->execute([$seller_id]);
$total_withdrawn = $stmt->fetchColumn() ?: 0.00;

// C. Total Pending Requests (Locked funds)
$stmt = $pdo->prepare("SELECT SUM(amount) FROM withdrawals WHERE seller_id = ? AND status = 'Pending'");
$stmt->execute([$seller_id]);
$pending_withdrawals = $stmt->fetchColumn() ?: 0.00;

// D. AVAILABLE BALANCE
$available_balance = $lifetime_earnings - $total_withdrawn - $pending_withdrawals;

// --- 2. HANDLE WITHDRAWAL REQUEST ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount']);
    $method = $_POST['payment_method'];
    $details = $_POST['account_details'];

    if ($amount <= 0) {
        $error = "Invalid amount.";
    } elseif ($amount > $available_balance) {
        $error = "Insufficient balance. You only have ₱" . number_format($available_balance, 2);
    } else {
        $stmt = $pdo->prepare("INSERT INTO withdrawals (seller_id, amount, payment_method, account_details) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$seller_id, $amount, $method, $details])) {
            // <--- 2. Trigger Modal on Success ---
            $message = "Withdrawal request submitted successfully!";
            $show_modal = true; 
            
            // Refresh balance
            $pending_withdrawals += $amount;
            $available_balance -= $amount;
        } else {
            $error = "System error. Please try again.";
        }
    }
}

// --- 3. FETCH HISTORY ---
$stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE seller_id = ? ORDER BY date_requested DESC");
$stmt->execute([$seller_id]);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Withdrawals - LaParfume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login.css">
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; min-height: 100vh; overflow-x: hidden; }
        .sidebar { width: 260px; background: rgba(22, 22, 22, 0.85); backdrop-filter: blur(10px); border-right: 1px solid rgba(255, 255, 255, 0.1); position: fixed; height: 100vh; top: 0; left: 0; padding: 20px; z-index: 100; display: flex; flex-direction: column; }
        .sidebar-brand .text-teal { color: #1dd1a1; }
        .main-content { margin-left: 260px; padding: 30px; }
        .nav-link { color: #aaa; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background-color: #1dd1a1; color: #000; font-weight: 600; }
        .sidebar .mt-auto { margin-top: auto !important; }

        /* Wallet Card */
        .wallet-card {
            background: linear-gradient(135deg, #161616, #222);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .balance-title { font-size: 0.9rem; color: #aaa; text-transform: uppercase; letter-spacing: 1px; }
        .balance-amount { font-size: 2.5rem; font-weight: 700; color: #1dd1a1; }
        
        /* Request Form */
        .glass-panel {
            background: rgba(30, 30, 30, 0.6);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 30px;
        }
        .form-control, .form-select {
            background-color: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 12px;
            border-radius: 8px;
        }
        .form-control:focus, .form-select:focus {
            background-color: rgba(0, 0, 0, 0.5);
            border-color: #1dd1a1;
            color: #fff;
            box-shadow: none;
        }

        /* --- DROPDOWN FIX START --- */
        /* This ensures the options inside the dropdown are dark so the white text is visible */
        .form-select option {
            background-color: #222;
            color: #fff;
        }
        /* --- DROPDOWN FIX END --- */

        /* Table */
        .table-custom { width: 100%; border-collapse: separate; border-spacing: 0 10px; margin-top: 20px; }
        .table-custom th { color: #aaa; padding: 15px; border-bottom: 1px solid #333; text-align: left; }
        .table-custom td { background: rgba(255, 255, 255, 0.02); padding: 15px; color: #fff; }
        .table-custom tr td:first-child { border-top-left-radius: 10px; border-bottom-left-radius: 10px; }
        .table-custom tr td:last-child { border-top-right-radius: 10px; border-bottom-right-radius: 10px; }

        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; }
        .status-Pending { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
        .status-Approved { background: rgba(25, 135, 84, 0.15); color: #198754; }
        .status-Rejected { background: rgba(220, 53, 69, 0.15); color: #dc3545; }

        .glow-blob { position: absolute; width: 500px; height: 500px; background: radial-gradient(circle, rgba(29,209,161,0.1) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -100px; left: -100px; }
        .blob-2 { bottom: -100px; right: -100px; }

        /* Modal Styles */
        .modal-content { background: #1a1a1a; border: 1px solid #333; color: white; }
        .modal-header { border-bottom: 1px solid #333; }
        .modal-footer { border-top: 1px solid #333; }
        .btn-close { filter: invert(1); }
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
            <a href="orders.php" class="nav-link"><i class="fas fa-shopping-bag"></i> Orders</a>
            <a href="my_reviews.php" class="nav-link"><i class="fas fa-star"></i> Reviews</a>
            <a href="withdrawals.php" class="nav-link active"><i class="fas fa-wallet"></i> Withdrawals</a>
            <a href="store_settings.php" class="nav-link"><i class="fas fa-store-alt"></i> Store Settings</a>
        </nav>
        <div class="mt-auto"><a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="wallet-card">
            <div>
                <div class="balance-title">Available Balance</div>
                <div class="balance-amount">₱<?php echo number_format($available_balance, 2); ?></div>
                <div class="text-muted small mt-1">Total Lifetime Earnings: ₱<?php echo number_format($lifetime_earnings, 2); ?></div>
            </div>
            <div class="text-end">
                <div class="text-warning small mb-1"><i class="fas fa-clock me-1"></i> Pending: ₱<?php echo number_format($pending_withdrawals, 2); ?></div>
                <div class="text-muted small"><i class="fas fa-check-circle me-1"></i> Withdrawn: ₱<?php echo number_format($total_withdrawn, 2); ?></div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="glass-panel h-100">
                    <h5 class="text-white mb-4">Request Payout</h5>
                    
                    <?php if($message && !$show_modal): ?><div class="alert alert-success bg-dark border-success text-success"><?php echo $message; ?></div><?php endif; ?>
                    <?php if($error): ?><div class="alert alert-danger bg-dark border-danger text-danger"><?php echo $error; ?></div><?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="text-muted small mb-1">Amount to Withdraw</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-muted">₱</span>
                                <input type="number" name="amount" class="form-control" step="0.01" max="<?php echo $available_balance; ?>" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted small mb-1">Payment Method</label>
                            <select name="payment_method" class="form-select" required>
                                <option value="GCash">GCash</option>
                                <option value="Bank Transfer">Bank Transfer (BDO/BPI)</option>
                                <option value="Maya">Maya</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="text-muted small mb-1">Account Details</label>
                            <textarea name="account_details" class="form-control" rows="3" placeholder="e.g. GCash Name & Number" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-bold" style="background: #1dd1a1; border: none; color: black; padding: 12px;">Submit Request</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="glass-panel">
                    <h5 class="text-white mb-0">Withdrawal History</h5>
                    
                    <table class="table-custom">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($history) > 0): ?>
                                <?php foreach($history as $row): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['date_requested'])); ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($row['payment_method']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['account_details']); ?></small>
                                    </td>
                                    <td class="fw-bold text-white">₱<?php echo number_format($row['amount'], 2); ?></td>
                                    <td><span class="status-badge status-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No withdrawal history.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="fw-bold text-white">Request Submitted!</h3>
                    <p class="text-muted">Your withdrawal request has been received and is pending Admin approval.</p>
                    <button type="button" class="btn btn-success w-100 fw-bold mt-3" data-bs-dismiss="modal" style="background: #1dd1a1; border:none; color:black;">Okay, Got it</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 4. JS Trigger for Success Modal
        <?php if($show_modal): ?>
            var myModal = new bootstrap.Modal(document.getElementById('successModal'));
            myModal.show();
        <?php endif; ?>
    </script>

</body>
</html>