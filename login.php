<?php
session_start();
require_once 'config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        $user = null;
        $user_type = '';

        // 1. Check if user is an ADMIN
        $stmt = $pdo->prepare("SELECT admin_id as id, full_name, email, password FROM admins WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        if ($row = $stmt->fetch()) {
            $user = $row;
            $user_type = 'admin';
        }

        // 2. If not admin, check if user is a SELLER
        if (!$user) {
            // UPDATED: Removed 'AND is_active = 1' so we can find banned sellers too
            $stmt = $pdo->prepare("SELECT seller_id as id, full_name, email, password, is_verified, is_active FROM sellers WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($row = $stmt->fetch()) {
                // A. Check if Account is Banned/Deactivated
                if ($row['is_active'] == 0) {
                    $error = 'Your seller account has been banned. Please contact support.';
                } 
                // B. Check if Pending Approval
                elseif ($row['is_verified'] == 0) {
                    $error = 'Your seller account is pending approval by the Admin.';
                } 
                // C. Account is Valid
                else {
                    $user = $row;
                    $user_type = 'seller';
                }
            }
        }

        // 3. If not seller (and no error), check if user is a CUSTOMER
        if (!$user && empty($error)) {
            $stmt = $pdo->prepare("SELECT customer_id as id, full_name, email, password FROM customers WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            if ($row = $stmt->fetch()) {
                $user = $row;
                $user_type = 'customer';
            }
        }

        // FINAL LOGIN LOGIC
        if ($user && empty($error)) {
            if (password_verify($password, $user['password'])) {
                // Login Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user_type;
                
                // Update last login time
                $table = $user_type . 's'; // admins, sellers, customers
                $id_field = ($user_type == 'customer' ? 'customer' : $user_type) . '_id';
                $pdo->prepare("UPDATE $table SET last_login = NOW() WHERE $id_field = ?")->execute([$user['id']]);
                
                // --- ADD LOGGING HERE ---
                if (!function_exists('logActivity')) {
                    function logActivity($pdo, $type, $message) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO activity_logs (log_type, message) VALUES (?, ?)");
                            $stmt->execute([$type, $message]);
                        } catch (Exception $e) {}
                    }
                }

                $role_label = ucfirst($user_type); 
                logActivity($pdo, 'User', "$role_label Login: " . $user['full_name']);
                // ------------------------

                // Redirect based on role
                switch ($user_type) {
                    case 'admin':
                        header('Location: admin/admin_dashboard.php');
                        break;
                    case 'seller':
                        header('Location: seller/dashboard.php');
                        break;
                    default:
                        header('Location: index.php');
                }
                exit();
            } else {
                $error = 'Invalid email or password';
            }
        } elseif (empty($error)) {
            // Only set generic error if specific error (like "Deactivated") wasn't set
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LaParfume</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
</head>
<body>
    
    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <div class="top-banner">
        Flash Sale For Some Perfume And Free Express Delivery – OFF 50%! <a href="shop.php">ShopNow</a>
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php" class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Parfume</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="signup.php">Sign Up</a></li>
                </ul>
                <div class="d-flex align-items-center mt-3 mt-lg-0">
                    <div class="search-wrapper me-3 d-none d-lg-flex">
                        <input type="text" class="search-input" placeholder="What are you looking for?">
                        <button class="search-btn"><i class="fas fa-search"></i></button>
                    </div>
                    <button class="mobile-search-btn d-lg-none"><i class="fas fa-search"></i></button>
                    <div class="nav-icons">
                        <button class="icon-btn" title="Wishlist"><i class="far fa-heart"></i></button>
                        <button class="icon-btn" title="Cart"><i class="fas fa-shopping-cart"></i></button>
                        <button class="icon-btn" title="Account"><i class="far fa-user"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <section class="login-section">
        <div class="login-card">
            <h1>Login to LaParfume</h1>
            <p class="login-subtitle">Enter your details below</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm" autocomplete="off">
                <input type="text" name="fake_username" style="display:none;">
                <input type="password" name="fake_password" style="display:none;">

                <div class="mb-4">
                    <input type="text" name="email" class="form-control" placeholder="Email Address" required autocomplete="new-password">
                </div>

                <div class="mb-3 password-wrapper">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Password" required autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="far fa-eye" id="toggleIcon"></i>
                    </button>
                </div>

                <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                        
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Log In
                </button>
            </form>
            
            <p class="signup-text">
                Don't have an account? <a href="signup.php">Sign Up</a>
            </p>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; ITP - 7 LaParfume System.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]').value;
            const password = this.querySelector('input[name="password"]').value;
            if (!email || !password) {
                e.preventDefault();
            }
        });

        document.querySelectorAll('.nav-link').forEach(link => {
            if (link.href === window.location.href) {
                link.classList.add('active');
            }
        });
    </script>
</body>
</html>