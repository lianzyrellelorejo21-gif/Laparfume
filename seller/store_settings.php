<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php'); exit();
}

$seller_id = $_SESSION['user_id'];
$message = '';
$message_type = 'success'; 

// Fetch Current Data
$seller = $pdo->prepare("SELECT * FROM sellers WHERE seller_id = ?");
$seller->execute([$seller_id]);
$data = $seller->fetch(PDO::FETCH_ASSOC);

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Get Text Inputs
    $business_name = $_POST['business_name'];
    $phone = $_POST['phone'];
    $desc = $_POST['business_description'];
    
    // 2. Handle Logo Upload
    if (!empty($_FILES['store_logo']['name'])) {
        $file = $_FILES['store_logo'];
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed_exts)) {
                // Ensure directory exists
                $upload_dir = "../images/sellers/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                // Generate Filename (store_ID.jpg)
                $new_name = "store_" . $seller_id . "." . $ext;
                $upload_path = $upload_dir . $new_name;

                // Move File
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // Update Database with filename
                    $stmt = $pdo->prepare("UPDATE sellers SET business_logo = ? WHERE seller_id = ?");
                    $stmt->execute([$new_name, $seller_id]);
                    
                    // Update local variable to show new image immediately
                    $data['business_logo'] = $new_name;
                } else {
                    $message = "Failed to upload image. Check folder permissions.";
                    $message_type = "danger";
                }
            } else {
                $message = "Invalid file type. Only JPG, PNG, GIF allowed.";
                $message_type = "danger";
            }
        }
    }

    // 3. Update Text Info
    if ($message_type === 'success') {
        $stmt = $pdo->prepare("UPDATE sellers SET business_name = ?, phone = ?, business_description = ? WHERE seller_id = ?");
        if ($stmt->execute([$business_name, $phone, $desc, $seller_id])) {
            $message = "Store settings saved successfully!";
            // Refresh data to show updates
            $seller->execute([$seller_id]);
            $data = $seller->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "Error updating settings.";
            $message_type = "danger";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store Settings - LaParfume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login.css">
    
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; overflow-x: hidden; }
        .sidebar { width: 260px; background: rgba(22, 22, 22, 0.85); backdrop-filter: blur(10px); border-right: 1px solid rgba(255,255,255,0.1); position: fixed; height: 100vh; padding: 20px; display: flex; flex-direction: column; }
        .main-content { margin-left: 260px; padding: 30px; }
        .nav-link { color: #aaa; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: rgba(29, 209, 161, 0.1); color: #1dd1a1; }
        .form-card { background: rgba(22, 22, 22, 0.6); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 30px; }
        .form-control { background-color: #2b2b2b; border: 1px solid #444; color: #fff; padding: 12px; }
        .form-control:focus { background-color: #333; border-color: #1dd1a1; color: #fff; box-shadow: none; }
        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -200px; left: -200px; } .blob-2 { bottom: -200px; right: -200px; }
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
            <a href="withdrawals.php" class="nav-link"><i class="fas fa-wallet"></i> Withdrawals</a>
            <a href="store_settings.php" class="nav-link active"><i class="fas fa-store-alt"></i> Store Settings</a>
        </nav>
        <div class="mt-auto"><a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <h2 class="fw-bold mb-4">Store Settings</h2>
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> bg-dark border-<?php echo $message_type; ?> text-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="row g-4">
                    <div class="col-md-12 text-center mb-3">
                        <div style="width: 100px; height: 100px; border-radius: 50%; background: #333; margin: 0 auto; overflow: hidden; border: 2px solid #1dd1a1;">
                            <?php 
                                $logoPath = !empty($data['business_logo']) ? '../images/sellers/'.$data['business_logo'] : 'https://via.placeholder.com/100/1dd1a1/000?text=LOGO';
                            ?>
                            <img src="<?php echo $logoPath; ?>?v=<?php echo time(); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <label class="btn btn-sm btn-outline-light mt-3" style="cursor: pointer;">
                            Change Logo <input type="file" name="store_logo" hidden onchange="this.form.submit()">
                        </label>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Store Name</label>
                        <input type="text" name="business_name" class="form-control" value="<?php echo htmlspecialchars($data['business_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Store Description / Bio</label>
                        <textarea name="business_description" class="form-control" rows="3"><?php echo htmlspecialchars($data['business_description'] ?? ''); ?></textarea>
                        <div class="form-text text-muted">Tell customers what makes your perfumes special.</div>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-success fw-bold" style="background: #1dd1a1; border: none; color: black; padding: 10px 30px;">Save Settings</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</body>
</html>