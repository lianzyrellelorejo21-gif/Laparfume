<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$customer_id = $_SESSION['user_id'];
$is_logged_in = true;

// Remove Logic
if(isset($_GET['remove'])) {
    $stmt = $pdo->prepare("DELETE FROM wishlist WHERE wishlist_id = ? AND customer_id = ?");
    $stmt->execute([$_GET['remove'], $customer_id]);
    header("Location: wishlist.php");
    exit();
}

// Fetch Wishlist Items
// Added 'stock' to the query to show availability
$sql = "SELECT w.wishlist_id, p.product_id, p.product_name, p.price, p.discount_price, p.image, p.stock 
        FROM wishlist w 
        JOIN products p ON w.product_id = p.product_id 
        WHERE w.customer_id = ?
        ORDER BY w.date_added DESC";
$wishlist_items = $pdo->prepare($sql);
$wishlist_items->execute([$customer_id]);
$items = $wishlist_items->fetchAll(PDO::FETCH_ASSOC);

// --- FETCH CUSTOMER IMAGE LOGIC (ADDED) ---
$user_profile_pic = "images/Profile.jpg"; // Default fallback

try {
    // Fetch the user's specific image filename
    $stmt_img = $pdo->prepare("SELECT profile_image FROM customers WHERE customer_id = ?");
    $stmt_img->execute([$customer_id]);
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
    <title>My Wishlist - LaParfume</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    <link rel="stylesheet" href="assets/home.css"> 
    
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }
        
        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: -1; }
        .blob-1 { top: -200px; left: -200px; } .blob-2 { bottom: -200px; right: -200px; }
        
        /* Wishlist Grid Card */
        .wishlist-card {
            background: rgba(30, 30, 30, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
            transition: transform 0.3s ease, border-color 0.3s ease;
            position: relative;
        }
        .wishlist-card:hover { transform: translateY(-5px); border-color: #1dd1a1; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        
        .card-img-wrap { position: relative; height: 250px; overflow: hidden; background: #161616; }
        .card-img-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .wishlist-card:hover .card-img-wrap img { transform: scale(1.05); }
        
        .btn-remove {
            position: absolute; top: 10px; right: 10px;
            background: rgba(0,0,0,0.6); color: #ff4757;
            border: none; width: 35px; height: 35px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: 0.2s; z-index: 2;
        }
        .btn-remove:hover { background: #ff4757; color: white; }
        
        .stock-badge {
            position: absolute; top: 10px; left: 10px;
            font-size: 0.75rem; font-weight: 600;
            padding: 4px 10px; border-radius: 4px;
            z-index: 2;
        }
        .in-stock { background: rgba(29, 209, 161, 0.9); color: black; }
        .out-stock { background: rgba(255, 71, 87, 0.9); color: white; }
        
        .card-body { padding: 20px; }
        .product-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 5px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: white; text-decoration: none; display: block; }
        .product-title:hover { color: #1dd1a1; }
        
        .btn-cart {
            width: 100%; background: transparent; 
            border: 1px solid #1dd1a1; color: #1dd1a1;
            padding: 10px; border-radius: 8px; font-weight: 600;
            transition: 0.3s; margin-top: 15px;
        }
        .btn-cart:hover { background: #1dd1a1; color: black; }
        .btn-cart:disabled { border-color: #555; color: #555; cursor: not-allowed; }
        .btn-cart:disabled:hover { background: transparent; color: #555; }
    </style>
</head>
<body>
    
    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>
    
    <nav class="navbar navbar-expand-lg navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php" class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Parfume</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>
                <div class="nav-icons text-white d-flex align-items-center gap-3">
                    <a href="wishlist.php" class="icon-btn text-warning" title="My Wishlist"><i class="fas fa-heart"></i></a>
                    <a href="cart.php" class="icon-btn"><i class="fas fa-shopping-cart"></i></a>
                    
                    <a href="customer/account.php" class="icon-btn d-flex align-items-center justify-content-center" title="Profile">
                        <img src="<?php echo htmlspecialchars($user_profile_pic); ?>?v=<?php echo time(); ?>" 
                             alt="Profile" 
                             style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid #1dd1a1;">
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4 flex-grow-1">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold m-0">My Wishlist <span class="text-muted fs-5">(<?php echo count($items); ?>)</span></h2>
            <a href="shop.php" class="btn btn-sm btn-outline-light px-3 rounded-pill">Continue Shopping</a>
        </div>
        
        <?php if(count($items) > 0): ?>
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php foreach($items as $item): ?>
                <?php 
                    $in_stock = $item['stock'] > 0;
                    $on_sale = ($item['discount_price'] > 0 && $item['discount_price'] < $item['price']);
                ?>
                <div class="col">
                    <div class="wishlist-card h-100 d-flex flex-column">
                        
                        <div class="card-img-wrap">
                            <span class="stock-badge <?php echo $in_stock ? 'in-stock' : 'out-stock'; ?>">
                                <?php echo $in_stock ? 'In Stock' : 'Out of Stock'; ?>
                            </span>
                            
                            <a href="wishlist.php?remove=<?php echo $item['wishlist_id']; ?>" 
                               class="btn-remove" onclick="return confirm('Remove from wishlist?');" title="Remove">
                                <i class="fas fa-times"></i>
                            </a>
                            
                            <a href="product_details.php?id=<?php echo $item['product_id']; ?>">
                                <img src="<?php echo getProductImage($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                            </a>
                        </div>

                        <div class="card-body d-flex flex-column flex-grow-1">
                            <a href="product_details.php?id=<?php echo $item['product_id']; ?>" class="product-title">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </a>
                            
                            <div class="mb-2">
                                <?php if($on_sale): ?>
                                    <span class="text-danger fw-bold me-2">₱<?php echo number_format($item['discount_price'], 2); ?></span>
                                    <span class="text-muted text-decoration-line-through small">₱<?php echo number_format($item['price'], 2); ?></span>
                                <?php else: ?>
                                    <span class="text-teal-400 fw-bold">₱<?php echo number_format($item['price'], 2); ?></span>
                                <?php endif; ?>
                            </div>

                            <form action="add_to_cart.php" method="POST" class="mt-auto w-100">
                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" name="add_to_cart" class="btn-cart" <?php echo !$in_stock ? 'disabled' : ''; ?>>
                                    <i class="fas fa-cart-plus me-2"></i><?php echo $in_stock ? 'Add to Cart' : 'Out of Stock'; ?>
                                </button>
                            </form>
                        </div>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <div class="mb-4 text-muted opacity-25">
                    <i class="far fa-heart fa-5x"></i>
                </div>
                <h4 class="text-muted">Your wishlist is empty</h4>
                <p class="text-white-50 mb-4">Save items you love here to buy later.</p>
                <a href="shop.php" class="btn btn-outline-light px-4 py-2 rounded-pill">Explore Products</a>
            </div>
        <?php endif; ?>
    </div>
    
    <footer class="mt-auto py-4 text-center border-top border-secondary position-relative z-1" style="background: #000;">
        <div class="container">
            <p class="mb-0 text-muted">ITP - 7 LaParfume System.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>