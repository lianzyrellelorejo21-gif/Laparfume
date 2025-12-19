<?php
session_start();
require_once 'config/database.php';

// Check login for Navbar
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - LaParfume</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    <link rel="stylesheet" href="assets/home.css">
    
    <style>
        /* Glass Card for About Section */
        .glass-panel {
            background: rgba(22, 22, 22, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 30px;
        }
        
        .feature-icon-box {
            background: rgba(29, 209, 161, 0.1);
            width: 80px; height: 80px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px auto;
            color: #1dd1a1;
            font-size: 2rem;
            transition: 0.3s;
        }
        
        .feature-card:hover .feature-icon-box {
            background: #1dd1a1;
            color: #000;
            box-shadow: 0 0 20px rgba(29, 209, 161, 0.4);
        }
    </style>
</head>
<body>
    
    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <nav class="navbar navbar-expand-lg navbar-dark mb-5">
        <div class="container">
            <a class="navbar-brand" href="index.php" class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Parfume</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="shop.php">Shop</a></li>
                    <li class="nav-item"><a class="nav-link active" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>
                <div class="nav-icons text-white d-flex align-items-center gap-3">
                    <a href="customer/cart.php" class="icon-btn"><i class="fas fa-shopping-cart"></i></a>
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

    <div class="container position-relative" style="z-index: 10;">
        
        <div class="glass-panel">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h5 class="text-teal-400 fw-bold text-uppercase mb-2">Our Story</h5>
                    <h1 class="display-5 fw-bold text-white mb-4">Redefining Luxury <br>Fragrances</h1>
                    <p class="text-muted leading-relaxed mb-4">
                        Founded by Lorejo, Manon-og and Antipuesto, LaParfume started with a simple mission: to make a Perfume System that accessible to everyone. 
                        We believe that a fragrance is more than just a smell—it's a memory, a statement, and a finishing touch to your personality.
                    </p>
                    <p class="text-muted leading-relaxed">
                        We curate only the finest authentic perfumes from around the Philippines, ensuring that every bottle you receive is 
                        a genuine masterpiece. Whether you are looking for a signature scent or a gift for a loved one, LaParfume is your trusted partner.
                    </p>
                    <img src="images/Signature.jpg" alt="Signature" style="height: 50px; opacity: 0.7;">
                </div>
                <div class="col-lg-6">
                    <div class="position-relative">
                        <img src="https://images.unsplash.com/photo-1595425970377-c9703cf48b6d?q=80&w=800&auto=format&fit=crop" 
                             class="img-fluid rounded-4 shadow-lg border border-secondary" alt="About LaParfume">
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 text-center mb-5">
            <div class="col-md-4">
                <div class="glass-panel h-100 feature-card">
                    <div class="feature-icon-box">
                        <i class="fas fa-gem"></i>
                    </div>
                    <h4 class="text-white">100% Authentic</h4>
                    <p class="text-muted mb-0">We guarantee that every product sold on our platform is 100% original and directly sourced.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-panel h-100 feature-card">
                    <div class="feature-icon-box">
                        <i class="fas fa-truck-fast"></i>
                    </div>
                    <h4 class="text-white">Express Delivery</h4>
                    <p class="text-muted mb-0">Get your favorite scents delivered to your doorstep within 2-3 business days nationwide.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-panel h-100 feature-card">
                    <div class="feature-icon-box">
                        <i class="fas fa-headset"></i>
                    </div>
                    <h4 class="text-white">24/7 Support</h4>
                    <p class="text-muted mb-0">Our fragrance experts are always available to help you find your perfect match.</p>
                </div>
            </div>
        </div>

    </div>

    <footer class="mt-auto py-4 text-center border-top border-secondary position-relative z-1">
        <div class="container">
            <p class="mb-0 text-muted">&copy; ITP - 7 LaParfume System.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>