<?php
session_start();
require_once 'config/database.php';

// --- HELPER FUNCTION FOR STARS ---
function renderStarRating($rating, $count) {
    $rating = round($rating * 2) / 2;
    $full = floor($rating);
    $half = ($rating - $full) > 0 ? 1 : 0;
    $empty = 5 - $full - $half;

    $html = '<div class="small text-warning">';
    for ($i = 0; $i < $full; $i++) $html .= '<i class="fas fa-star"></i> ';
    if ($half) $html .= '<i class="fas fa-star-half-alt"></i> ';
    for ($i = 0; $i < $empty; $i++) $html .= '<i class="far fa-star"></i> ';
    
    $displayText = $count > 0 ? "($rating)" : "(No reviews)";
    $html .= '<span class="text-white-50 ms-1" style="font-size: 0.75rem;">' . $displayText . '</span>';
    $html .= '</div>';
    return $html;
}

// --- FILTERING & SEARCH LOGIC ---
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$sort_option = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : ''; 

// Build Query
// MODIFIED: Updated subquery to count only 'Delivered' orders for total_sold
$sql = "SELECT p.*, s.business_name, s.full_name, 
        (SELECT COALESCE(SUM(oi.quantity), 0) 
         FROM order_items oi 
         JOIN orders o ON oi.order_id = o.order_id 
         WHERE oi.product_id = p.product_id AND o.status = 'Delivered') as total_sold,
        (SELECT AVG(rating) FROM reviews WHERE product_id = p.product_id) as avg_rating,
        (SELECT COUNT(*) FROM reviews WHERE product_id = p.product_id) as review_count
        FROM products p
        LEFT JOIN sellers s ON p.seller_id = s.seller_id
        WHERE p.is_active = 1 AND p.product_status = 'Approved'";

$params = [];

// 1. Search Filter
if (!empty($search_query)) {
    $sql .= " AND (p.product_name LIKE :search_name OR p.description LIKE :search_desc)";
    $params['search_name'] = "%$search_query%";
    $params['search_desc'] = "%$search_query%";
}

// 2. Category Filter
if ($category_filter != 'all') {
    $sql .= " AND p.category = :category";
    $params['category'] = $category_filter;
}

// 3. Sorting
switch ($sort_option) {
    case 'price_low': $sql .= " ORDER BY p.price ASC"; break;
    case 'price_high': $sql .= " ORDER BY p.price DESC"; break;
    case 'name': $sql .= " ORDER BY p.product_name ASC"; break;
    case 'newest': default: $sql .= " ORDER BY p.date_added DESC"; break;
}

// Execute
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $products = []; }

$is_logged_in = isset($_SESSION['user_id']);

// --- FETCH CUSTOMER IMAGE LOGIC (ADDED) ---
$user_profile_pic = "images/Profile.jpg"; // Default fallback

if ($is_logged_in) {
    try {
        // Fetch the user's specific image filename
        $stmt_img = $pdo->prepare("SELECT profile_image FROM customers WHERE customer_id = ?");
        $stmt_img->execute([$_SESSION['user_id']]);
        $row = $stmt_img->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['profile_image'])) {
            $db_img = $row['profile_image']; 
            
            // Check multiple folder possibilities
            $candidates = [
                "images/" . $db_img,            
                "customer/images/" . $db_img,   
                $db_img                         
            ];
            
            foreach ($candidates as $path) {
                if (file_exists($path)) {
                    $user_profile_pic = $path; // Found the custom image
                    break;
                }
            }
        }
    } catch (Exception $e) {
        // Ignore errors, keep default
    }
}

// Final Safety Check
if (!file_exists($user_profile_pic)) {
    $user_profile_pic = "https://via.placeholder.com/150/1dd1a1/000000?text=Profile";
}
// ------------------------------------------

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
    <title>Shop - LaParfume</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    <link rel="stylesheet" href="assets/home.css">
    
    <style>
        .sidebar-card { background: rgba(33, 37, 41, 0.7); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; }
        .sidebar-link { display: block; padding: 10px 15px; color: #aaa; text-decoration: none; transition: all 0.3s ease; border-radius: 5px; }
        .sidebar-link:hover, .sidebar-link.active { background: rgba(29, 209, 161, 0.1); color: #1dd1a1; padding-left: 20px; }
        
        .sold-out-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); z-index: 2; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .sold-out-badge { background-color: #ff4757; color: white; padding: 10px 20px; font-weight: bold; text-transform: uppercase; border-radius: 4px; transform: rotate(-10deg); border: 2px solid white; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        
        .btn-wishlist:hover { color: #ff4757 !important; transform: scale(1.1); transition: 0.2s; }
        
        /* SALE BADGE CSS */
        .sale-badge { background: #ff4757; color: white; font-weight: bold; padding: 5px 10px; border-radius: 4px; font-size: 0.8rem; box-shadow: 0 4px 10px rgba(255, 71, 87, 0.4); }
        
        /* NEW: Seller Badge Style */
        .seller-badge { font-size: 0.7rem; background: rgba(0,0,0,0.6); padding: 3px 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); color: #ccc; }

        /* Success Modal Styles */
        .modal-content { background: #1a1a1a; border: 1px solid #333; color: white; }
        .modal-header { border-bottom: 1px solid #333; }
        .modal-footer { border-top: 1px solid #333; }
        .btn-close { filter: invert(1); }

        /* --- ADDED: BUY NOW BUTTON STYLE --- */
        .btn-buy-now { background-color: #1dd1a1; color: #000; font-weight: 600; border: none; transition: 0.3s; }
        .btn-buy-now:hover { background-color: #15a07c; color: #fff; }
    </style>
</head>
<body>
    <div class="glow-blob blob-1"></div><div class="glow-blob blob-2"></div>

    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php" class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Parfume</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="shop.php">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>
                <div class="nav-icons text-white d-flex align-items-center gap-3">
                    <form action="shop.php" method="GET" class="search-wrapper d-none d-lg-flex me-2">
                        <input type="text" name="search" class="search-input" placeholder="Search..." value="<?php echo htmlspecialchars($search_query); ?>">
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

    <div class="container py-4 position-relative" style="z-index: 10;">
        <div class="row">
            
            <div class="col-lg-3 mb-4">
                <div class="sidebar-card p-4 sticky-top" style="top: 90px;">
                    <h5 class="text-white fw-bold mb-3">Refine By</h5>
                    <div class="mb-4">
                        <h6 class="text-teal-400 text-uppercase small fw-bold mb-2">Collection</h6>
                        <div class="d-flex flex-column gap-1">
                            <?php function getUrl($cat, $sort, $search) { return "?category=$cat&sort=$sort&search=$search"; } ?>
                            <a href="<?= getUrl('all', $sort_option, $search_query) ?>" class="sidebar-link <?= $category_filter == 'all' ? 'active' : '' ?>">All Products</a>
                            <a href="<?= getUrl('Women', $sort_option, $search_query) ?>" class="sidebar-link <?= $category_filter == 'Women' ? 'active' : '' ?>">Women's Perfume</a>
                            <a href="<?= getUrl('Men', $sort_option, $search_query) ?>" class="sidebar-link <?= $category_filter == 'Men' ? 'active' : '' ?>">Men's Cologne</a>
                            <a href="<?= getUrl('Unisex', $sort_option, $search_query) ?>" class="sidebar-link <?= $category_filter == 'Unisex' ? 'active' : '' ?>">Unisex Fragrances</a>
                            <a href="<?= getUrl('Gift Sets', $sort_option, $search_query) ?>" class="sidebar-link <?= $category_filter == 'Gift Sets' ? 'active' : '' ?>">Gift Sets</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4 p-3 sidebar-card">
                    <h2 class="h4 m-0 text-white">
                        <?php 
                            if (!empty($search_query)) echo 'Search results for: "' . htmlspecialchars($search_query) . '"';
                            elseif ($category_filter == 'all') echo "All Collections";
                            else echo htmlspecialchars($category_filter) . " Collection"; 
                        ?>
                    </h2>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light rounded-pill px-4 dropdown-toggle" type="button" data-bs-toggle="dropdown">Sort By</button>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="?category=<?= $category_filter ?>&sort=newest&search=<?= $search_query ?>">Newest</a></li>
                            <li><a class="dropdown-item" href="?category=<?= $category_filter ?>&sort=price_low&search=<?= $search_query ?>">Price: Low to High</a></li>
                            <li><a class="dropdown-item" href="?category=<?= $category_filter ?>&sort=price_high&search=<?= $search_query ?>">Price: High to Low</a></li>
                        </ul>
                    </div>
                </div>

                <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
                    <?php if (count($products) > 0): ?>
                        <?php foreach ($products as $product): ?>
                        
                        <?php 
                            $is_out_of_stock = ($product['stock'] <= 0); 
                            $on_sale = ($product['discount_price'] > 0 && $product['discount_price'] < $product['price']);
                            // Seller & Stats Data
                            $seller_name = !empty($product['business_name']) ? $product['business_name'] : ($product['full_name'] ?? 'LaParfume Official');
                            $sold_count = isset($product['total_sold']) ? $product['total_sold'] : 0;
                        ?>

                        <div class="col">
                            <div class="product-card d-flex flex-column h-100" style="background: rgba(33, 37, 41, 0.7); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; overflow: hidden;">
                                <div class="position-relative" style="padding-top: 100%; overflow: hidden;">
                                    <?php if ($is_out_of_stock): ?>
                                        <div class="sold-out-overlay"><div class="sold-out-badge">Sold Out</div></div>
                                    <?php endif; ?>
                                    
                                    <?php if ($on_sale): ?>
                                        <?php $percent = round((($product['price'] - $product['discount_price']) / $product['price']) * 100); ?>
                                        <div class="position-absolute top-0 start-0 m-2 sale-badge" style="z-index: 1;">
                                            -<?php echo $percent; ?>%
                                        </div>
                                    <?php endif; ?>
                                    
                                    <a href="product_details.php?id=<?php echo $product['product_id']; ?>">
                                        <img src="<?php echo getProductImage($product['image']); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover" style="transition: transform 0.3s ease;">
                                    </a>

                                    <form action="add_to_wishlist.php" method="POST" class="position-absolute top-0 end-0 p-2">
                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-light rounded-circle shadow-sm btn-wishlist" style="z-index: 3; position: relative;"><i class="far fa-heart"></i></button>
                                    </form>
                                </div>
                                
                                <div class="p-3 d-flex flex-column flex-grow-1">
                                    <div class="mb-1"><span class="seller-badge"><i class="fas fa-store me-1"></i> <?php echo htmlspecialchars($seller_name); ?></span></div>
                                    
                                    <h5 class="text-white mb-1 fs-6 text-truncate"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                    
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <?php 
                                            $rating_val = !empty($product['avg_rating']) ? $product['avg_rating'] : 0;
                                            $review_num = !empty($product['review_count']) ? $product['review_count'] : 0;
                                            echo renderStarRating($rating_val, $review_num); 
                                        ?>
                                        <div class="small text-white-50" style="font-size: 0.75rem;">
                                            <?php echo $sold_count; ?> sold
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <?php if ($on_sale): ?>
                                            <span class="text-danger fw-bold me-2">₱<?php echo number_format($product['discount_price'], 2); ?></span>
                                            <span class="text-muted text-decoration-line-through small">₱<?php echo number_format($product['price'], 2); ?></span>
                                        <?php else: ?>
                                            <div class="text-teal-400 fw-bold">₱<?php echo number_format($product['price'], 2); ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if (!$is_out_of_stock): ?>
                                            <div class="small mt-1 <?php echo ($product['stock'] < 5) ? 'text-warning' : 'text-white-50'; ?>" style="font-size: 0.8rem;">
                                                <i class="<?php echo ($product['stock'] < 5) ? 'fas fa-fire' : 'fas fa-box-open'; ?> me-1"></i>
                                                <?php echo ($product['stock'] < 5) ? "Only " . $product['stock'] . " left!" : "Stocks: " . $product['stock']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-auto">
                                        <?php if ($is_out_of_stock): ?>
                                            <button class="btn btn-sm btn-secondary w-100" disabled style="opacity: 0.5; cursor: not-allowed;"><i class="fas fa-ban me-2"></i>Out of Stock</button>
                                        <?php else: ?>
                                            <form action="add_to_cart.php" method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                
                                                <button type="button" name="add_to_cart" class="btn btn-sm btn-outline-light flex-grow-1" onclick="addToCart(this)">
                                                    <i class="fas fa-cart-plus"></i>
                                                </button>
                                                
                                                <button type="submit" name="buy_now" class="btn btn-sm btn-buy-now flex-grow-1">
                                                    Buy Now
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <h3 class="text-muted">No products found matching "<?php echo htmlspecialchars($search_query); ?>"</h3>
                            <a href="shop.php" class="btn btn-outline-light mt-3">View All Products</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="cartModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center p-4">
                <div class="modal-body">
                    <div class="mb-3">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                    </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addToCart(btnElement) {
            // Find parent form
            const form = btnElement.closest('form');
            const formData = new FormData(form);
            
            // Append a specific flag to say this is an ajax request
            formData.append('add_to_cart', '1');

            fetch('add_to_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Show success modal
                    var myModal = new bootstrap.Modal(document.getElementById('cartModal'));
                    myModal.show();
                } else if (data.status === 'error') {
                    // Show POPUP (Alert) for Stock Issues
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert("An unexpected error occurred.");
            });
        }
    </script>
</body>
</html>