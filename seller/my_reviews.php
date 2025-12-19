<?php
session_start();
require_once '../config/database.php';

// 1. Check Seller Login
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// 2. Fetch Review Statistics
$stats_sql = "SELECT COUNT(*) as total_count, AVG(r.rating) as avg_rating 
              FROM reviews r 
              JOIN products p ON r.product_id = p.product_id 
              WHERE p.seller_id = ?";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute([$seller_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

$total_reviews = $stats['total_count'] ?? 0;
$average_rating = number_format($stats['avg_rating'] ?? 0, 1);

// 3. Fetch Reviews
$sql = "SELECT r.*, p.product_name, p.image as product_image, c.full_name, c.profile_image
        FROM reviews r
        JOIN products p ON r.product_id = p.product_id
        JOIN customers c ON r.customer_id = c.customer_id
        WHERE p.seller_id = ?
        ORDER BY r.date_posted DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$seller_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function
function renderStarRating($rating) {
    $full = floor($rating);
    $half = ($rating - $full) > 0 ? 1 : 0;
    $empty = 5 - $full - $half;
    $html = '<div class="text-warning small">';
    for ($i=0; $i<$full; $i++) $html .= '<i class="fas fa-star"></i>';
    if ($half) $html .= '<i class="fas fa-star-half-alt"></i>';
    for ($i=0; $i<$empty; $i++) $html .= '<i class="far fa-star text-secondary" style="opacity:0.3"></i>';
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reviews - Seller Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/login.css">
    
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; overflow-x: hidden; }
        
        /* Sidebar & Layout */
        .sidebar { width: 260px; background-color: #111; border-right: 1px solid #333; position: fixed; top: 0; left: 0; height: 100vh; padding: 20px; display: flex; flex-direction: column; z-index: 100; }
        .main-content { margin-left: 260px; padding: 40px; min-height: 100vh; position: relative; z-index: 2; }
        .nav-link { color: #aaa; padding: 12px 15px; border-radius: 8px; margin-bottom: 5px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background-color: #1dd1a1; color: #000; font-weight: 600; }
        .sidebar .mt-auto { margin-top: auto !important; }

        /* Stats Widgets */
        .stat-card {
            background: rgba(22, 22, 22, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); border-color: #1dd1a1; }
        .stat-icon {
            width: 60px; height: 60px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
        }
        .bg-icon-1 { background: rgba(29, 209, 161, 0.1); color: #1dd1a1; }
        .bg-icon-2 { background: rgba(255, 193, 7, 0.1); color: #ffc107; }

        /* Review Grid */
        .review-card {
            background: #161616;
            border: 1px solid #333;
            border-radius: 16px;
            padding: 0;
            overflow: hidden;
            height: 100%;
            transition: all 0.3s ease;
            position: relative;
        }
        .review-card:hover {
            border-color: #1dd1a1;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .review-header {
            padding: 20px;
            border-bottom: 1px solid #222;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #222; }

        .review-body { padding: 20px; min-height: 100px; }
        .quote-icon { font-size: 1.5rem; color: #333; margin-bottom: 10px; display: block; }

        .review-footer {
            background: rgba(255,255,255,0.02);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-top: 1px solid #222;
        }
        .prod-thumb { width: 40px; height: 40px; border-radius: 6px; object-fit: cover; }

        /* Background Effects */
        .glow-blob { position: absolute; width: 500px; height: 500px; background: radial-gradient(circle, rgba(29,209,161,0.08) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -100px; left: 200px; } .blob-2 { bottom: -100px; right: -100px; }
    </style>
</head>
<body>

    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

   <div class="sidebar">
        <div class="mb-5 px-2"><h3 class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Seller</span></h3></div>
        <nav class="nav flex-column">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="add_product.php" class="nav-link"><i class="fas fa-plus-circle"></i> Add Product</a>
            <a href="my_products.php" class="nav-link"><i class="fas fa-box"></i> My Products</a>
            <a href="stock_logs.php" class="nav-link"><i class="fas fa-history"></i> Stock Logs</a>
            <a href="orders.php" class="nav-link"><i class="fas fa-shopping-bag"></i> Orders</a>
            <a href="my_reviews.php" class="nav-link active"><i class="fas fa-star"></i> Reviews</a>
            <a href="withdrawals.php" class="nav-link"><i class="fas fa-wallet"></i> Withdrawals</a>
            <a href="store_settings.php" class="nav-link"><i class="fas fa-store-alt"></i> Store Settings</a>
        </nav>
        <div class="mt-auto"><a href="../logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0">Customer Feedback</h2>
            <div class="text-muted small">Manage your product reputation</div>
        </div>

        <div class="row mb-5">
            <div class="col-md-6 mb-3 mb-md-0">
                <div class="stat-card">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-1">Total Reviews</div>
                        <h1 class="fw-bold m-0"><?php echo number_format($total_reviews); ?></h1>
                    </div>
                    <div class="stat-icon bg-icon-1"><i class="fas fa-comment-dots"></i></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stat-card">
                    <div>
                        <div class="text-muted small text-uppercase fw-bold mb-1">Overall Rating</div>
                        <div class="d-flex align-items-baseline gap-2">
                            <h1 class="fw-bold m-0 text-warning"><?php echo $average_rating; ?></h1>
                            <small class="text-muted">/ 5.0</small>
                        </div>
                    </div>
                    <div class="stat-icon bg-icon-2"><i class="fas fa-star"></i></div>
                </div>
            </div>
        </div>

        <?php if (empty($reviews)): ?>
            <div class="text-center py-5 rounded-4 border border-secondary border-opacity-25" style="background: rgba(22,22,22,0.5);">
                <i class="far fa-smile-beam fa-3x mb-3 text-muted opacity-50"></i>
                <h5 class="text-white">No reviews yet</h5>
                <p class="text-muted m-0">Reviews will appear here once customers rate your products.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($reviews as $review): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="review-card d-flex flex-column">
                        
                        <div class="review-header">
                            <div class="user-info">
                                <?php $avatar = !empty($review['profile_image']) ? '../images/users/'.$review['profile_image'] : 'https://via.placeholder.com/40/333/fff?text='.strtoupper(substr($review['full_name'],0,1)); ?>
                                <img src="<?php echo $avatar; ?>" class="user-avatar">
                                <div>
                                    <h6 class="mb-0 fw-bold text-white" style="font-size: 0.95rem;"><?php echo htmlspecialchars($review['full_name']); ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('M d, Y', strtotime($review['date_posted'])); ?></small>
                                </div>
                            </div>
                            <?php echo renderStarRating($review['rating']); ?>
                        </div>

                        <div class="review-body">
                            <i class="fas fa-quote-left quote-icon"></i>
                            <p class="text-secondary m-0" style="line-height: 1.6; font-style: italic;">
                                "<?php echo htmlspecialchars($review['comment']); ?>"
                            </p>
                        </div>

                        <div class="review-footer mt-auto">
                            <img src="../images/<?php echo $review['product_image']; ?>" class="prod-thumb">
                            <div class="text-truncate">
                                <small class="text-teal-400 fw-bold d-block" style="font-size: 0.7rem; color: #1dd1a1;">PRODUCT</small>
                                <span class="text-white small fw-bold d-block text-truncate"><?php echo htmlspecialchars($review['product_name']); ?></span>
                            </div>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>