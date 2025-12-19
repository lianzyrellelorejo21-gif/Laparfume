<?php
session_start();
require_once 'config/database.php';

// --- HELPER FUNCTION FOR STARS ---
function renderStarRating($rating, $count) {
    $rating = round($rating * 2) / 2; // Round to nearest 0.5
    $full = floor($rating);
    $half = ($rating - $full) > 0 ? 1 : 0;
    $empty = 5 - $full - $half;

    $html = '<div class="d-flex align-items-center text-warning small">'; 
    
    // Full Stars
    for ($i = 0; $i < $full; $i++) {
        $html .= '<i class="fas fa-star"></i> ';
    }
    // Half Star
    if ($half) {
        $html .= '<i class="fas fa-star-half-alt"></i> ';
    }
    // Empty Stars
    for ($i = 0; $i < $empty; $i++) {
        $html .= '<i class="far fa-star"></i> ';
    }
    
    // Show count if available
    $displayText = $count > 0 ? "($rating)" : "(No reviews)";
    $html .= '<span class="text-white-50 ms-2" style="font-size: 0.8rem;">' . $displayText . '</span>';
    $html .= '</div>';
    
    return $html;
}

// --- 0. FETCH DYNAMIC BANNER ---
try {
    $stmt_home = $pdo->prepare("SELECT title FROM homepage WHERE section_type = 'Banner'");
    $stmt_home->execute();
    $home_data = $stmt_home->fetch(PDO::FETCH_ASSOC);
    $hero_title = !empty($home_data['title']) ? $home_data['title'] : "Up to 50% Off Luxury Scents";
} catch (Exception $e) {
    $hero_title = "Up to 50% Off Luxury Scents";
}

// --- 1. BEST SELLERS LOGIC ---
try {
    $sql = "SELECT p.*, s.business_name, s.full_name, 
            (SELECT COALESCE(SUM(oi.quantity), 0) 
             FROM order_items oi 
             JOIN orders o ON oi.order_id = o.order_id 
             WHERE oi.product_id = p.product_id AND o.status = 'Delivered') as total_sold,
            (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
            (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
            FROM products p 
            LEFT JOIN sellers s ON p.seller_id = s.seller_id
            WHERE p.is_active = 1 AND p.product_status = 'Approved'
            ORDER BY total_sold DESC, p.product_id DESC 
            LIMIT 4";

    $stmt = $pdo->query($sql);
    $best_sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $best_sellers = [];
}

// --- 2. NEW ARRIVALS LOGIC ---
try {
    $sql_new = "SELECT p.*, s.business_name, s.full_name, 
                (SELECT COALESCE(SUM(oi.quantity), 0) 
                 FROM order_items oi 
                 JOIN orders o ON oi.order_id = o.order_id 
                 WHERE oi.product_id = p.product_id AND o.status = 'Delivered') as total_sold,
                (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
                (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
                FROM products p 
                LEFT JOIN sellers s ON p.seller_id = s.seller_id 
                WHERE p.is_active = 1 AND p.product_status = 'Approved'
                ORDER BY p.date_added DESC LIMIT 4";
    $new_arrivals = $pdo->query($sql_new)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $new_arrivals = []; }

// --- 3. FLASH SALE LOGIC ---
try {
    $sql_flash = "SELECT p.*, s.business_name, s.full_name, 
                  (SELECT COALESCE(SUM(oi.quantity), 0) 
                   FROM order_items oi 
                   JOIN orders o ON oi.order_id = o.order_id 
                   WHERE oi.product_id = p.product_id AND o.status = 'Delivered') as total_sold,
                  (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
                  (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
                  FROM products p 
                  LEFT JOIN sellers s ON p.seller_id = s.seller_id 
                  WHERE p.is_active = 1 AND p.product_status = 'Approved' 
                  AND p.discount_price > 0 AND p.discount_price < p.price 
                  LIMIT 4"; 
    $flash_sales = $pdo->query($sql_flash)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $flash_sales = []; }

$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';

// --- SMART PROFILE PICTURE LOGIC ---
$user_profile_pic = "images/Profile.jpg"; 

if ($is_logged_in) {
    try {
        $stmt_img = $pdo->prepare("SELECT profile_image FROM customers WHERE customer_id = ?");
        $stmt_img->execute([$_SESSION['user_id']]);
        $row = $stmt_img->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['profile_image'])) {
            $db_img = $row['profile_image']; 
            $candidates = [
                "images/" . $db_img,            
                "customer/images/" . $db_img,   
                $db_img                          
            ];
            foreach ($candidates as $path) {
                if (file_exists($path)) {
                    $user_profile_pic = $path; 
                    break;
                }
            }
        }
    } catch (Exception $e) { }
}

if (!file_exists($user_profile_pic)) {
    $user_profile_pic = "https://via.placeholder.com/150/1dd1a1/000000?text=Profile";
}

function getProductImage($imageName) {
    $path = "images/" . $imageName;
    return file_exists($path) ? $path : "https://via.placeholder.com/300x300/161616/333333?text=No+Image";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - LaParfume</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    <link rel="stylesheet" href="assets/home.css">

    <style>
        .glass-panel { background: rgba(22, 22, 22, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 14px; padding: 30px; margin-bottom: 20px; }
        .feature-icon-box { background: rgba(29, 209, 161, 0.1); width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; color: #1dd1a1; font-size: 2rem; transition: 0.3s; }
        .feature-card:hover .feature-icon-box { background: #1dd1a1; color: #000; box-shadow: 0 0 20px rgba(29, 209, 161, 0.4); }
        .section-header { display: flex; justify-content: space-between; align-items: end; margin-bottom: 1.5rem; }
        .btn-buy-now { background-color: #1dd1a1; color: #000; font-weight: 600; border: none; }
        .btn-buy-now:hover { background-color: #15a07c; color: #fff; }
        .flash-badge { background: #ff4757; color: white; font-weight: bold; font-size: 0.8rem; padding: 5px 10px; border-radius: 4px; box-shadow: 0 4px 10px rgba(255, 71, 87, 0.4); }
        .btn-wishlist:hover { color: #ff4757 !important; transform: scale(1.1); transition: 0.2s; }
        
        .sold-out-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 5; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .sold-out-badge { background-color: #ff4757; color: white; padding: 8px 15px; font-weight: bold; text-transform: uppercase; border-radius: 4px; transform: rotate(-10deg); border: 2px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.5); font-size: 0.9rem; }
        .seller-badge { font-size: 0.7rem; background: rgba(0,0,0,0.6); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); color: #ccc; }
        .badge-new { background: #1dd1a1; color: #000; font-weight: 700; padding: 5px 10px; font-size: 0.7rem; letter-spacing: 1px; }
        
        /* Category Card & Newsletter */
        .cat-card { background: rgba(33, 37, 41, 0.6); border: 1px solid rgba(255,255,255,0.1); padding: 20px; border-radius: 12px; text-align: center; transition: 0.3s; text-decoration: none; display: block; }
        .cat-card:hover { background: rgba(29, 209, 161, 0.1); border-color: #1dd1a1; transform: translateY(-5px); }
        .newsletter-box { background: linear-gradient(135deg, #161616, #000); border: 1px solid #333; border-radius: 15px; padding: 40px; position: relative; overflow: hidden; }

        /* --- UPDATED MODAL STYLES --- */
        #selectionModal .modal-content { 
            background-color: #161616; 
            border: 1px solid #333; 
            border-radius: 12px; 
            overflow: hidden; 
            color: #fff;
        }
        #selectionModal .modal-body { padding: 0; }
        
        .modal-layout { display: flex; flex-wrap: wrap; }
        
        /* Left Column */
        .modal-left { 
            background-color: #1a1a1a; 
            padding: 2rem; 
            position: relative; 
            display: flex; 
            flex-direction: column; 
            justify-content: center; 
            align-items: center;
        }
        .modal-product-img { max-width: 100%; height: auto; max-height: 250px; object-fit: contain; margin-bottom: 1.5rem; }
        .modal-stats { width: 100%; margin-top: auto; }
        
        /* Ribbon */
        .ribbon-new {
            position: absolute; top: -5px; left: 10px; width: 40px; height: 50px;
            background: #1dd1a1; 
            color: #000; font-weight: bold; font-family: 'Times New Roman', serif; 
            text-align: center; line-height: 40px; font-size: 0.8rem;
            clip-path: polygon(0 0, 100% 0, 100% 100%, 50% 80%, 0 100%);
            box-shadow: 0 4px 10px rgba(29, 209, 161, 0.4);
        }

        /* Right Column */
        .modal-right { padding: 2.5rem; display: flex; flex-direction: column; justify-content: center; }
        .modal-title { font-family: 'Playfair Display', serif; font-size: 2.5rem; margin-bottom: 0.5rem; line-height: 1.1; }
        .modal-price { font-size: 2rem; font-weight: 700; margin-bottom: 1rem; color: #fff; font-family: 'Poppins', sans-serif; }
        .modal-stock-warn { color: #e67e22; font-weight: 500; font-size: 1.1rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px; }
        
        /* Buttons in Modal */
        .modal-actions { display: flex; gap: 15px; margin-top: 1rem; }
        .btn-modal-cart-icon {
            width: 50px; height: 50px; 
            border: 1px solid #555; border-radius: 8px; background: #222;
            color: #ccc; display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; transition: 0.2s;
        }
        .btn-modal-cart-icon:hover { border-color: #fff; color: #fff; background: #333; }
        
        .btn-modal-main-buy {
            flex-grow: 1; height: 50px;
            background: linear-gradient(90deg, #1dd1a1, #10ac84);
            border: none; border-radius: 8px;
            color: #000; font-weight: 600; font-size: 1.1rem;
            box-shadow: 0 0 15px rgba(29, 209, 161, 0.3);
            transition: 0.2s;
        }
        .btn-modal-main-buy:hover { opacity: 0.9; transform: translateY(-2px); }

        /* Quantity/Size (Subtle) */
        .modal-options { margin-bottom: 1.5rem; border-top: 1px solid #333; padding-top: 1rem; }
        .qty-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 5px; }
        .modal-qty-input { background: transparent; border: 1px solid #444; color: white; width: 60px; text-align: center; border-radius: 4px; padding: 5px; }

        /* Size Buttons */
        .size-btn { border-color: #444; color: #ccc; margin-right: 5px; }
        .size-btn:hover { border-color: #1dd1a1; color: #1dd1a1; background: transparent; }
        .size-btn.active { background-color: #1dd1a1; border-color: #1dd1a1; color: #000; font-weight: bold; }

    </style>
</head>
<body>
    
    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <div class="top-banner">
        Flash Sale For Some Perfume And Free Express Delivery – OFF 50%!
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php" class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Parfume</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>
                <div class="nav-icons text-white d-flex align-items-center gap-3">
                    <form action="shop.php" method="GET" class="search-wrapper d-none d-lg-flex me-2">
                        <input type="text" name="search" class="search-input" placeholder="Search..." required>
                        <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <a href="wishlist.php" class="icon-btn" title="My Wishlist"><i class="far fa-heart"></i></a>
                    <a href="cart.php" class="icon-btn"><i class="fas fa-shopping-cart"></i></a>
                    
                    <?php if ($is_logged_in): ?>
                        <a href="customer/account.php" class="icon-btn d-flex align-items-center justify-content-center" title="Profile">
                      <img src="<?php echo htmlspecialchars($user_profile_pic); ?>?v=<?php echo time(); ?>" 
                           alt="Profile" 
                           style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid #1dd1a1;">
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-sm btn-outline-light px-3 rounded-pill">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="hero-container">
            <div class="category-sidebar d-none d-lg-block">
                <a href="shop.php?category=Women" class="cat-link">Women's Perfume <i class="fas fa-chevron-right text-muted fs-6"></i></a>
                <a href="shop.php?category=Men" class="cat-link">Men's Cologne <i class="fas fa-chevron-right text-muted fs-6"></i></a>
                <a href="shop.php?category=Unisex" class="cat-link">Unisex Fragrances <i class="fas fa-chevron-right text-muted fs-6"></i></a>
                <a href="shop.php?category=Gift Sets" class="cat-link">Gift Sets <i class="fas fa-chevron-right text-muted fs-6"></i></a>
                <a href="shop.php" class="cat-link">All Products <i class="fas fa-chevron-right text-muted fs-6"></i></a>
            </div>
            <div class="hero-banner">
                <div class="hero-content">
                    <div class="text-teal-400 fw-bold mb-2"><i class="fa-solid fa-spray-can-sparkles"></i>    Laparfume Scent Collections</div>
                    <h1 class="hero-title"><?php echo htmlspecialchars($hero_title); ?></h1>
                    <a href="shop.php" class="btn btn-hero mt-4">Shop Now <i class="fas fa-arrow-right ms-2"></i></a>
                </div>
            </div>
        </div>
    </div>

    <section class="py-5 position-relative" style="z-index: 10;">
        <div class="container">
            <div class="section-header">
                <div>
                    <div class="text-teal-400 fw-bold small mb-1">Just Arrived</div>
                    <h2 class="section-title m-0">New Products</h2>
                </div>
                <a href="shop.php?sort=newest" class="btn btn-sm btn-outline-light rounded-pill px-4">View All</a>
            </div>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
                <?php foreach ($new_arrivals as $product): ?>
                <?php 
                    $is_out_of_stock = ($product['stock'] <= 0); 
                    $seller_name = !empty($product['business_name']) ? $product['business_name'] : ($product['full_name'] ?? 'LaParfume Official');
                    $sold_count = isset($product['total_sold']) ? $product['total_sold'] : 0;
                    $product_img = getProductImage($product['image']);
                    
                    $rating_val = !empty($product['avg_rating']) ? $product['avg_rating'] : 0;
                    $review_num = !empty($product['review_count']) ? $product['review_count'] : 0;
                    $star_html = renderStarRating($rating_val, $review_num);
                ?>
                <div class="col">
                    <div class="product-card d-flex flex-column h-100">
                        <div class="position-relative" style="padding-top: 100%; overflow: hidden;">
                            <?php if ($is_out_of_stock): ?>
                                <div class="sold-out-overlay"><div class="sold-out-badge">Sold Out</div></div>
                            <?php endif; ?>

                            <img src="<?php echo $product_img; ?>" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover">
                            <div class="position-absolute top-0 start-0 m-2 badge-new rounded">NEW</div>
                            
                            <form action="add_to_wishlist.php" method="POST" class="position-absolute top-0 end-0 p-2">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-light rounded-circle shadow-sm btn-wishlist" style="z-index: 6;"><i class="far fa-heart"></i></button>
                            </form>
                        </div>
                        
                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <div class="mb-1"><span class="seller-badge"><i class="fas fa-store me-1"></i> <?php echo htmlspecialchars($seller_name); ?></span></div>
                            <h5 class="text-white mb-1 fs-6 text-truncate"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                            
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <?php echo $star_html; ?>
                                <div class="small text-white-50" style="font-size: 0.75rem;">
                                    <?php echo $sold_count; ?> sold
                                </div>
                            </div>
                            <div class="text-teal-400 fw-bold mb-2">₱<?php echo number_format($product['price'], 2); ?></div>
                            
                            <?php if (!$is_out_of_stock): ?>
                                <div class="small mb-3 <?php echo ($product['stock'] < 5) ? 'text-warning' : 'text-white-50'; ?>" style="font-size: 0.8rem;">
                                    <i class="<?php echo ($product['stock'] < 5) ? 'fas fa-fire' : 'fas fa-box-open'; ?> me-1"></i>
                                    <?php echo ($product['stock'] < 5) ? "Only " . $product['stock'] . " left!" : "Stocks: " . $product['stock']; ?>
                                </div>
                            <?php else: ?>
                                <div class="mb-3"></div>
                            <?php endif; ?>

                            <div class="mt-auto">
                                <?php if ($is_out_of_stock): ?>
                                    <button class="btn btn-sm btn-secondary w-100" disabled style="opacity: 0.5; cursor: not-allowed;"><i class="fas fa-ban me-2"></i>Out of Stock</button>
                                <?php else: ?>
                                    <div class="d-flex gap-2">
                                        <button type="button" 
                                            class="btn btn-sm btn-outline-light flex-grow-1" 
                                            onclick="openSelectionModal(this)"
                                            data-id="<?php echo $product['product_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                            data-price="<?php echo $product['price']; ?>"
                                            data-img="<?php echo $product_img; ?>"
                                            data-stock="<?php echo $product['stock']; ?>"
                                            data-sold="<?php echo $sold_count; ?>"
                                            data-star-html="<?php echo htmlspecialchars($star_html); ?>"
                                        ><i class="fas fa-cart-plus"></i></button>

                                        <button type="button" 
                                            class="btn btn-sm btn-buy-now flex-grow-1"
                                            onclick="openSelectionModal(this)"
                                            data-id="<?php echo $product['product_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                            data-price="<?php echo $product['price']; ?>"
                                            data-img="<?php echo $product_img; ?>"
                                            data-stock="<?php echo $product['stock']; ?>"
                                            data-sold="<?php echo $sold_count; ?>"
                                            data-star-html="<?php echo htmlspecialchars($star_html); ?>"
                                        >Buy Now</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if (count($flash_sales) > 0): ?>
    <section class="py-5 position-relative" style="background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(29, 209, 161, 0.05) 100%); border-top: 1px solid rgba(0, 0, 0, 0.1); border-bottom: 1px solid rgba(255, 71, 87, 0.1);">
        <div class="container">
            <div class="section-header align-items-center">
                <div>
                    <div class="text-danger fw-bold small mb-1"><i class="fas fa-bolt me-1"></i>Limited Time Offer</div>
                    <div class="d-flex align-items-center gap-4">
                        <h2 class="section-title m-0 text-white">Flash Sales</h2>
                        <div class="countdown-box" id="flash-sale-timer">
                             <div class="time-unit"><div class="time-val" id="days">00</div><div class="time-label">Days</div></div><div class="time-val">:</div>
                             <div class="time-unit"><div class="time-val" id="hours">00</div><div class="time-label">Hours</div></div><div class="time-val">:</div>
                             <div class="time-unit"><div class="time-val" id="minutes">00</div><div class="time-label">Mins</div></div><div class="time-val">:</div>
                             <div class="time-unit"><div class="time-val" id="seconds">00</div><div class="time-label">Secs</div></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
                <?php foreach ($flash_sales as $product): ?>
                <?php 
                    $percent_off = round((($product['price'] - $product['discount_price']) / $product['price']) * 100); 
                    $is_out_of_stock = ($product['stock'] <= 0);
                    $seller_name = !empty($product['business_name']) ? $product['business_name'] : ($product['full_name'] ?? 'LaParfume Official');
                    $sold_count = isset($product['total_sold']) ? $product['total_sold'] : 0;
                    $product_img = getProductImage($product['image']);
                    $rating_val = !empty($product['avg_rating']) ? $product['avg_rating'] : 0;
                    $review_num = !empty($product['review_count']) ? $product['review_count'] : 0;
                    $star_html = renderStarRating($rating_val, $review_num);
                ?>
                <div class="col">
                    <div class="product-card d-flex flex-column h-100" style="border: 1px solid rgba(255, 71, 87, 0.3);">
                        <div class="position-relative" style="padding-top: 100%; overflow: hidden;">
                            <?php if ($is_out_of_stock): ?>
                                <div class="sold-out-overlay"><div class="sold-out-badge">Sold Out</div></div>
                            <?php endif; ?>
                            <img src="<?php echo $product_img; ?>" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover">
                            <div class="position-absolute top-0 start-0 m-2 flash-badge">-<?php echo $percent_off; ?>%</div>
                            <form action="add_to_wishlist.php" method="POST" class="position-absolute top-0 end-0 p-2">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-light rounded-circle shadow-sm btn-wishlist" style="z-index: 6;"><i class="far fa-heart"></i></button>
                            </form>
                        </div>
                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <div class="mb-1"><span class="seller-badge"><i class="fas fa-store me-1"></i> <?php echo htmlspecialchars($seller_name); ?></span></div>
                            <h5 class="text-white mb-1 fs-6 text-truncate"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <?php echo $star_html; ?>
                                <div class="small text-white-50" style="font-size: 0.75rem;"><?php echo $sold_count; ?> sold</div>
                            </div>
                            <div class="mb-2">
                                <span class="text-danger fw-bold me-2">₱<?php echo number_format($product['discount_price'], 2); ?></span>
                                <span class="text-muted text-decoration-line-through small">₱<?php echo number_format($product['price'], 2); ?></span>
                            </div>
                            <?php if (!$is_out_of_stock): ?>
                                <div class="small mb-3 <?php echo ($product['stock'] < 5) ? 'text-warning' : 'text-white-50'; ?>" style="font-size: 0.8rem;">
                                    <i class="<?php echo ($product['stock'] < 5) ? 'fas fa-fire' : 'fas fa-box-open'; ?> me-1"></i>
                                    <?php echo ($product['stock'] < 5) ? "Only " . $product['stock'] . " left!" : "Stocks: " . $product['stock']; ?>
                                </div>
                            <?php else: ?>
                                <div class="mb-3"></div>
                            <?php endif; ?>
                            <div class="mt-auto">
                                <?php if ($is_out_of_stock): ?>
                                    <button class="btn btn-sm btn-secondary w-100" disabled style="opacity: 0.5;"><i class="fas fa-ban me-2"></i>Out of Stock</button>
                                <?php else: ?>
                                    <div class="d-flex gap-2">
                                        <button type="button" 
                                            class="btn btn-sm btn-outline-danger flex-grow-1" 
                                            onclick="openSelectionModal(this)"
                                            data-id="<?php echo $product['product_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                            data-price="<?php echo $product['discount_price']; ?>"
                                            data-img="<?php echo $product_img; ?>"
                                            data-stock="<?php echo $product['stock']; ?>"
                                            data-sold="<?php echo $sold_count; ?>"
                                            data-star-html="<?php echo htmlspecialchars($star_html); ?>"
                                        ><i class="fas fa-cart-plus"></i></button>

                                        <button type="button" 
                                            class="btn btn-sm btn-danger flex-grow-1 fw-bold"
                                            onclick="openSelectionModal(this)"
                                            data-id="<?php echo $product['product_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                            data-price="<?php echo $product['discount_price']; ?>"
                                            data-img="<?php echo $product_img; ?>"
                                            data-stock="<?php echo $product['stock']; ?>"
                                            data-sold="<?php echo $sold_count; ?>"
                                            data-star-html="<?php echo htmlspecialchars($star_html); ?>"
                                        >Buy Now</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="py-4 position-relative" style="background: linear-gradient(180deg, rgba(29, 209, 161, 0.05) 0%, rgba(0,0,0,0) 100%); z-index: 10;">
        <div class="container">
           <div class="section-header">
                <div>
                    <div class="text-teal-400 fw-bold small mb-1">This Month</div>
                    <h2 class="section-title m-0">Best Sellers</h2>
                </div>
                <a href="shop.php" class="btn btn-sm btn-outline-light rounded-pill px-4">View All</a>
            </div>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
                <?php foreach ($best_sellers as $product): ?>
                <?php 
                    $is_out_of_stock = ($product['stock'] <= 0); 
                    $seller_name = !empty($product['business_name']) ? $product['business_name'] : ($product['full_name'] ?? 'LaParfume Official');
                    $sold_count = isset($product['total_sold']) ? $product['total_sold'] : 0;
                    $product_img = getProductImage($product['image']);
                    $rating_val = !empty($product['avg_rating']) ? $product['avg_rating'] : 0;
                    $review_num = !empty($product['review_count']) ? $product['review_count'] : 0;
                    $star_html = renderStarRating($rating_val, $review_num);
                ?>
                <div class="col">
                    <div class="product-card d-flex flex-column h-100">
                        <div class="position-relative" style="padding-top: 100%; overflow: hidden;">
                            <?php if ($is_out_of_stock): ?>
                                <div class="sold-out-overlay"><div class="sold-out-badge">Sold Out</div></div>
                            <?php endif; ?>
                            <img src="<?php echo $product_img; ?>" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover">
                            <?php if(isset($product['total_sold']) && $product['total_sold'] > 5): ?>
                                <div class="position-absolute top-0 start-0 m-2 px-2 py-1 bg-danger text-white small rounded fw-bold">HOT</div>
                            <?php endif; ?>
                            <form action="add_to_wishlist.php" method="POST" class="position-absolute top-0 end-0 p-2">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <button type="submit" class="btn btn-sm btn-light rounded-circle shadow-sm btn-wishlist" style="z-index: 6;"><i class="far fa-heart"></i></button>
                            </form>
                        </div>
                        <div class="p-3 d-flex flex-column flex-grow-1">
                            <div class="mb-1"><span class="seller-badge"><i class="fas fa-store me-1"></i> <?php echo htmlspecialchars($seller_name); ?></span></div>
                            <h5 class="text-white mb-1 fs-6 text-truncate"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <?php echo $star_html; ?>
                                <div class="small text-white-50" style="font-size: 0.75rem;"><?php echo $sold_count; ?> sold</div>
                            </div>
                            <div class="text-teal-400 fw-bold mb-2">₱<?php echo number_format($product['price'], 2); ?></div>
                            <?php if (!$is_out_of_stock): ?>
                                <div class="small mb-3 <?php echo ($product['stock'] < 5) ? 'text-warning' : 'text-white-50'; ?>" style="font-size: 0.8rem;">
                                    <i class="<?php echo ($product['stock'] < 5) ? 'fas fa-fire' : 'fas fa-box-open'; ?> me-1"></i>
                                    <?php echo ($product['stock'] < 5) ? "Only " . $product['stock'] . " left!" : "Stocks: " . $product['stock']; ?>
                                </div>
                            <?php else: ?>
                                <div class="mb-3"></div>
                            <?php endif; ?>
                            <div class="mt-auto">
                                <?php if ($is_out_of_stock): ?>
                                    <button class="btn btn-sm btn-secondary w-100" disabled style="opacity: 0.5;"><i class="fas fa-ban me-2"></i>Out of Stock</button>
                                <?php else: ?>
                                    <div class="d-flex gap-2">
                                        <button type="button" 
                                            class="btn btn-sm btn-outline-light flex-grow-1" 
                                            onclick="openSelectionModal(this)"
                                            data-id="<?php echo $product['product_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                            data-price="<?php echo $product['price']; ?>"
                                            data-img="<?php echo $product_img; ?>"
                                            data-stock="<?php echo $product['stock']; ?>"
                                            data-sold="<?php echo $sold_count; ?>"
                                            data-star-html="<?php echo htmlspecialchars($star_html); ?>"
                                        ><i class="fas fa-cart-plus"></i></button>

                                        <button type="button" 
                                            class="btn btn-sm btn-buy-now flex-grow-1"
                                            onclick="openSelectionModal(this)"
                                            data-id="<?php echo $product['product_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($product['product_name']); ?>"
                                            data-price="<?php echo $product['price']; ?>"
                                            data-img="<?php echo $product_img; ?>"
                                            data-stock="<?php echo $product['stock']; ?>"
                                            data-sold="<?php echo $sold_count; ?>"
                                            data-star-html="<?php echo htmlspecialchars($star_html); ?>"
                                        >Buy Now</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <div class="container py-5">
        <div class="row">
            <div class="col-md-12">
                <div class="p-5 rounded-3 text-center position-relative overflow-hidden border border-secondary" style="background: #000;">
                    <div class="position-absolute top-50 start-50 translate-middle rounded-circle" 
                         style="width: 400px; height: 400px; background: radial-gradient(circle, rgba(29,209,161,0.1) 0%, rgba(0,0,0,0) 70%); filter: blur(40px);"></div>
                    <div class="position-relative z-1">
                        <h2 class="fw-bold mb-3">Enhance Your Fragrance Experience</h2>
                        <p class="text-muted mb-4 mx-auto" style="max-width: 600px;">Discover our curated selection of premium perfumes that define elegance and personality.</p>
                        <a href="shop.php" class="btn btn-success px-5 py-2 fw-bold" style="background-color: #1dd1a1; border: none; color: black;">Buy Now</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cartModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <div class="mb-3"><i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i></div>
                    <h3 class="fw-bold text-white">Added to Cart!</h3>
                    <p class="text-muted">The product has been successfully added to your shopping cart.</p>
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Continue Shopping</button>
                        <a href="cart.php" class="btn btn-success fw-bold" style="background: #1dd1a1; border:none; color:black;">View Cart</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="selectionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <form id="modalForm" action="add_to_cart.php" method="POST">
                    <input type="hidden" name="product_id" id="modal_product_id">
                    
                    <div class="modal-body modal-layout">
                        <div class="col-md-5 modal-left">
                            <div class="ribbon-new">NEW</div>
                            <img id="modal_img" src="" alt="Product" class="modal-product-img">
                            
                            <div class="modal-stats">
                                <div id="modal_stars_container" class="mb-1"></div>
                                <div class="text-white-50 small"><i class="fas fa-user me-1"></i> Sold: <span id="modal_sold" class="text-white fw-bold">0</span></div>
                            </div>
                        </div>

                        <div class="col-md-7 modal-right">
                            <h2 id="modal_name" class="modal-title">Product Name</h2>
                            <div class="modal-price">₱<span id="modal_price">0.00</span></div>
                            <div class="modal-stock-warn">
                                <i class="fas fa-fire"></i> Only <span id="modal_stock">0</span> left!
                            </div>

                            <div class="modal-options">
                                <div class="mb-3">
                                    <div class="qty-label">Capacity</div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-light btn-sm size-btn" onclick="selectSize(this, '50ml')">50ml</button>
                                        <button type="button" class="btn btn-outline-light btn-sm size-btn" onclick="selectSize(this, '75ml')">75ml</button>
                                        <button type="button" class="btn btn-outline-light btn-sm size-btn active" onclick="selectSize(this, '100ml')">100ml</button>
                                    </div>
                                    <input type="hidden" name="size" id="modal_size" value="100ml">
                                </div>

                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <div class="qty-label">Quantity</div>
                                        <input type="number" name="quantity" id="modal_quantity" class="modal-qty-input" value="1" min="1">
                                    </div>
                                </div>
                            </div>

                            <div class="modal-actions">
                                <button type="button" name="add_to_cart" class="btn-modal-cart-icon" onclick="confirmModalAction('cart')">
                                    <i class="fas fa-shopping-cart"></i>
                                </button>
                                <button type="button" class="btn-modal-main-buy" onclick="confirmModalAction('buy')">
                                    Buy Now
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Timer Logic
        const countDownDate = new Date().getTime() + (3 * 24 * 60 * 60 * 1000); 
        const x = setInterval(function() {
            const now = new Date().getTime();
            const distance = countDownDate - now;
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            if(document.getElementById("days")) {
                document.getElementById("days").innerHTML = (days < 10 ? "0" : "") + days;
                document.getElementById("hours").innerHTML = (hours < 10 ? "0" : "") + hours;
                document.getElementById("minutes").innerHTML = (minutes < 10 ? "0" : "") + minutes;
                document.getElementById("seconds").innerHTML = (seconds < 10 ? "0" : "") + seconds;
            }
        }, 1000);

        // --- NEW MODAL JS LOGIC ---
        var selectionModal = new bootstrap.Modal(document.getElementById('selectionModal'));
        var currentStock = 0;

        function openSelectionModal(btn) {
            // 1. Get data
            const id = btn.getAttribute('data-id');
            const name = btn.getAttribute('data-name');
            const price = btn.getAttribute('data-price');
            const img = btn.getAttribute('data-img');
            const stock = parseInt(btn.getAttribute('data-stock'));
            const sold = btn.getAttribute('data-sold');
            const starHtml = btn.getAttribute('data-star-html'); // Get pre-rendered stars

            currentStock = stock;

            // 2. Populate
            document.getElementById('modal_product_id').value = id;
            document.getElementById('modal_img').src = img;
            document.getElementById('modal_name').innerText = name;
            document.getElementById('modal_price').innerText = parseFloat(price).toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('modal_stock').innerText = stock;
            document.getElementById('modal_sold').innerText = sold;
            document.getElementById('modal_stars_container').innerHTML = starHtml; // Inject stars
            
            // Reset quantity & size defaults
            const qtyInput = document.getElementById('modal_quantity');
            qtyInput.value = 1;
            qtyInput.max = stock;
            
            // Reset active size to 100ml
            document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
            document.querySelector('.size-btn:last-child').classList.add('active'); 
            document.getElementById('modal_size').value = '100ml';

            selectionModal.show();
        }

        // --- NEW FUNCTION TO HANDLE SIZE SELECTION ---
        function selectSize(btn, size) {
            // Update hidden input
            document.getElementById('modal_size').value = size;
            // Visual update
            document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }

        function confirmModalAction(type) {
            const form = document.getElementById('modalForm');
            const addCartBtn = form.querySelector('button[name="add_to_cart"]');

            if (type === 'cart') {
                addToCart(addCartBtn);
                selectionModal.hide();
            } else {
                const hiddenBuy = document.createElement('input');
                hiddenBuy.type = 'hidden';
                hiddenBuy.name = 'buy_now';
                hiddenBuy.value = 'true';
                form.appendChild(hiddenBuy);
                form.submit();
            }
        }

        // --- AJAX ADD TO CART ---
        function addToCart(btnElement) {
            if (btnElement.name === 'buy_now') return; 

            const form = btnElement.closest('form');
            const formData = new FormData(form);
            formData.append('add_to_cart', '1');

            fetch('add_to_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    var myModal = new bootstrap.Modal(document.getElementById('cartModal'));
                    myModal.show();
                } else {
                    alert(data.message); 
                }
            })
            .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>