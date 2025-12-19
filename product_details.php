<?php
session_start();
require_once 'config/database.php';

// 1. Get Product ID
if (!isset($_GET['id'])) {
    header("Location: shop.php");
    exit();
}
$product_id = $_GET['id'];
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$message = "";

// --- 2. HANDLE REVIEW SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
    if (!$user_id) {
        header("Location: login.php");
        exit();
    }

    $rating = intval($_POST['rating']);
    $comment = htmlspecialchars($_POST['comment']); 

    // Check if user already reviewed
    $check = $pdo->prepare("SELECT review_id FROM reviews WHERE product_id = ? AND customer_id = ?");
    $check->execute([$product_id, $user_id]);

    if ($check->rowCount() > 0) {
        $message = "You have already reviewed this product.";
        $msg_type = "warning";
    } else {
        $stmt = $pdo->prepare("INSERT INTO reviews (product_id, customer_id, rating, comment, date_posted) VALUES (?, ?, ?, ?, NOW())");
        if ($stmt->execute([$product_id, $user_id, $rating, $comment])) {
            $message = "Review submitted successfully!";
            $msg_type = "success";
        }
    }
}

// --- 3. FETCH PRODUCT DETAILS ---
$stmt = $pdo->prepare("
    SELECT p.*, s.business_name, 
    (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
    (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
    FROM products p 
    JOIN sellers s ON p.seller_id = s.seller_id 
    WHERE p.product_id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) { header("Location: shop.php"); exit(); }

// --- 4. FETCH REVIEWS ---
$stmt_reviews = $pdo->prepare("
    SELECT r.*, c.full_name, c.profile_image 
    FROM reviews r 
    JOIN customers c ON r.customer_id = c.customer_id 
    WHERE r.product_id = ? 
    ORDER BY r.date_posted DESC
");
$stmt_reviews->execute([$product_id]);
$reviews = $stmt_reviews->fetchAll(PDO::FETCH_ASSOC);

// Helper for Displaying Stars
function renderStarRating($rating) {
    $full = floor($rating);
    $half = ($rating - $full) > 0 ? 1 : 0;
    $empty = 5 - $full - $half;
    $html = '';
    for ($i=0; $i<$full; $i++) $html .= '<i class="fas fa-star text-warning"></i>';
    if ($half) $html .= '<i class="fas fa-star-half-alt text-warning"></i>';
    for ($i=0; $i<$empty; $i++) $html .= '<i class="far fa-star text-muted" style="opacity:0.3;"></i>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($product['product_name']); ?> - LaParfume</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    <style>
        body { background-color: #050505; color: #fff; font-family: 'Poppins', sans-serif; }
        
        /* Navbar Tweaks */
        .navbar { background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255,255,255,0.05); }

        /* Product Layout */
        .product-container { padding: 50px 0; }
        
        /* Sticky Image Container */
        .img-wrapper {
            background: #111;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.05);
            position: sticky;
            top: 100px; /* Makes it sticky */
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .product-img { width: 100%; max-width: 400px; filter: drop-shadow(0 15px 30px rgba(0,0,0,0.6)); transition: transform 0.3s; }
        .img-wrapper:hover .product-img { transform: scale(1.05) rotate(-2deg); }

        /* Details Column */
        .seller-tag { 
            background: rgba(29, 209, 161, 0.1); color: #1dd1a1; 
            padding: 5px 12px; border-radius: 30px; font-size: 0.85rem; 
            font-weight: 600; display: inline-block; margin-bottom: 15px; 
            border: 1px solid rgba(29, 209, 161, 0.2);
        }
        
        .product-title { font-size: 2.5rem; font-weight: 700; line-height: 1.2; letter-spacing: -0.5px; }
        .price-tag { font-size: 2rem; color: #fff; font-weight: 600; margin: 20px 0; display: flex; align-items: center; gap: 15px; }
        .price-tag span { font-size: 1rem; color: #aaa; font-weight: 400; text-decoration: line-through; }

        /* Quantity & Cart Buttons */
        .action-group { background: #161616; padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05); margin-top: 30px; }
        
        .qty-input-group { 
            display: flex; align-items: center; background: #000; 
            border: 1px solid #333; border-radius: 8px; overflow: hidden; width: 120px;
        }
        .qty-btn { 
            background: #222; border: none; color: #fff; width: 40px; height: 45px; 
            font-size: 1.2rem; cursor: pointer; transition: 0.2s; 
        }
        .qty-btn:hover { background: #333; }
        .qty-val { 
            background: #000; border: none; color: #fff; text-align: center; width: 40px; 
            font-weight: bold; pointer-events: none; /* User uses buttons */
        }

        .btn-add-cart {
            background: #1dd1a1; color: #000; font-weight: 700; border: none;
            padding: 12px 30px; border-radius: 8px; flex-grow: 1; font-size: 1.1rem;
            transition: all 0.3s;
        }
        .btn-add-cart:hover { background: #15a07c; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(29, 209, 161, 0.2); }

        /* Review Section */
        .review-section { margin-top: 80px; padding-top: 50px; border-top: 1px solid rgba(255,255,255,0.05); }
        
        /* Interactive Star Rating */
        .star-rating-box { display: flex; flex-direction: row-reverse; justify-content: start; gap: 5px; }
        .star-rating-box input { display: none; }
        .star-rating-box label { font-size: 1.8rem; color: #444; cursor: pointer; transition: 0.2s; }
        .star-rating-box label:hover, 
        .star-rating-box label:hover ~ label, 
        .star-rating-box input:checked ~ label { color: #ffc107; }

        .write-review-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 16px; padding: 25px; height: fit-content;
        }
        .review-list-card {
            background: #111; border-radius: 12px; padding: 20px; margin-bottom: 15px; border: 1px solid #222;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">LA<span style="color:#1dd1a1;">Parfume</span></a>
            <a href="shop.php" class="text-white text-decoration-none small fw-bold"><i class="fas fa-arrow-left me-2"></i>Back to Shop</a>
        </div>
    </nav>

    <div class="container product-container" style="margin-top: 60px;">
        <div class="row">
            <div class="col-lg-6 mb-5">
                <div class="img-wrapper">
                    <img src="images/<?php echo $product['image']; ?>" class="product-img" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                </div>
            </div>

            <div class="col-lg-6">
                <div class="ps-lg-4">
                    <div class="seller-tag"><i class="fas fa-store me-2"></i><?php echo htmlspecialchars($product['business_name']); ?></div>
                    
                    <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    
                    <div class="d-flex align-items-center mt-2 mb-4">
                        <div class="d-flex me-2"><?php echo renderStarRating($product['avg_rating']); ?></div>
                        <span class="text-muted small border-start border-secondary ps-2 ms-2">
                            <?php echo number_format($product['avg_rating'], 1); ?> Rating &bull; <?php echo $product['review_count']; ?> Reviews
                        </span>
                    </div>

                    <div class="price-tag">
                        ₱<?php echo number_format($product['price'], 2); ?>
                        </div>

                    <p class="text-secondary" style="line-height: 1.8; font-size: 0.95rem;">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </p>

                    <form action="add_to_cart.php" method="POST">
                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                        
                        <div class="action-group">
                            <div class="d-flex gap-3 align-items-end">
                                <div>
                                    <label class="text-muted small fw-bold mb-2 d-block">QUANTITY</label>
                                    <div class="qty-input-group">
                                        <button type="button" class="qty-btn" onclick="updateQty(-1)">-</button>
                                        <input type="number" name="quantity" id="qtyInput" value="1" min="1" class="qty-val" readonly>
                                        <button type="button" class="qty-btn" onclick="updateQty(1)">+</button>
                                    </div>
                                </div>
                                <button type="submit" name="add_to_cart" class="btn-add-cart h-100">
                                    <i class="fas fa-shopping-bag me-2"></i> Add to Cart
                                </button>
                            </div>
                            <div class="mt-3 text-muted small"><i class="fas fa-truck me-2"></i> Fast Delivery (2-3 Days) &bull; <i class="fas fa-shield-alt ms-2 me-2"></i> 100% Authentic</div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="review-section">
            <h3 class="fw-bold mb-4">Customer Reviews <span class="text-muted fs-5 ms-2">(<?php echo $product['review_count']; ?>)</span></h3>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msg_type ?? 'info'; ?> bg-dark border-<?php echo $msg_type ?? 'info'; ?> text-white mb-4">
                    <i class="fas fa-info-circle me-2"></i> <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-7 mb-4">
                    <?php if (count($reviews) > 0): ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div class="review-list-card">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex align-items-center">
                                        <?php $avatar = !empty($rev['profile_image']) ? 'images/users/' . $rev['profile_image'] : 'https://via.placeholder.com/45/1dd1a1/000?text=' . strtoupper(substr($rev['full_name'], 0, 1)); ?>
                                        <img src="<?php echo $avatar; ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid #222;">
                                        <div>
                                            <h6 class="mb-0 fw-bold text-white" style="font-size: 0.9rem;"><?php echo htmlspecialchars($rev['full_name']); ?></h6>
                                            <div class="small mt-0"><?php echo renderStarRating($rev['rating']); ?></div>
                                        </div>
                                    </div>
                                    <small class="text-secondary" style="font-size: 0.75rem;"><?php echo date('M d, Y', strtotime($rev['date_posted'])); ?></small>
                                </div>
                                <p class="text-secondary m-0 small ps-5" style="line-height: 1.6;"><?php echo htmlspecialchars($rev['comment']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-5 text-center border border-secondary rounded text-muted">
                            <i class="far fa-comments fa-3x mb-3" style="opacity:0.3"></i>
                            <p>No reviews yet for this product.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-5">
                    <div class="write-review-card sticky-top" style="top: 100px;">
                        <h5 class="fw-bold mb-3">Write a Review</h5>
                        <?php if ($user_id): ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">RATING</label>
                                    <div class="star-rating-box">
                                        <input type="radio" name="rating" id="s5" value="5" required><label for="s5" title="Excellent"><i class="fas fa-star"></i></label>
                                        <input type="radio" name="rating" id="s4" value="4"><label for="s4" title="Good"><i class="fas fa-star"></i></label>
                                        <input type="radio" name="rating" id="s3" value="3"><label for="s3" title="Average"><i class="fas fa-star"></i></label>
                                        <input type="radio" name="rating" id="s2" value="2"><label for="s2" title="Poor"><i class="fas fa-star"></i></label>
                                        <input type="radio" name="rating" id="s1" value="1"><label for="s1" title="Bad"><i class="fas fa-star"></i></label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-bold">YOUR FEEDBACK</label>
                                    <textarea name="comment" class="form-control bg-dark text-white border-secondary" rows="4" placeholder="How was the longevity and projection?" required></textarea>
                                </div>
                                <button type="submit" name="submit_review" class="btn btn-success w-100 fw-bold" style="background: #1dd1a1; border:none; color:black; padding: 10px;">Post Review</button>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-4 bg-dark rounded border border-secondary">
                                <p class="text-muted small mb-3">You must be logged in to post a review.</p>
                                <a href="login.php" class="btn btn-outline-light btn-sm px-4">Login</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quantity Spinner Logic
        function updateQty(change) {
            const input = document.getElementById('qtyInput');
            let newVal = parseInt(input.value) + change;
            if (newVal < 1) newVal = 1;
            input.value = newVal;
        }
    </script>
</body>
</html>