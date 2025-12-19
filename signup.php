<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Basic sanitization replacing custom function to ensure code runs
    $full_name = htmlspecialchars(trim($_POST['full_name'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $address = htmlspecialchars(trim($_POST['address'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT email FROM customers WHERE email = ?
                               UNION SELECT email FROM sellers WHERE email = ?
                               UNION SELECT email FROM admins WHERE email = ?");
        $stmt->execute([$email, $email, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Email already registered';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // ... (Previous validation logic) ...

try {
    // Insert into customers table
    $stmt = $pdo->prepare("INSERT INTO customers (full_name, email, password, phone, address, date_created) VALUES (?, ?, ?, ?, ?, NOW())");
    
    if ($stmt->execute([$full_name, $email, $hashed_password, $phone, $address])) {
        
        // --- ADD THIS BLOCK HERE ---
        // 1. Get the new Customer ID (Optional, but good for logs)
        $new_customer_id = $pdo->lastInsertId();

        // 2. Log the Activity
        // Ensure you include the helper function if it's not in config/database.php
        if (!function_exists('logActivity')) {
            function logActivity($pdo, $type, $message) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (log_type, message) VALUES (?, ?)");
                    $stmt->execute([$type, $message]);
                } catch (Exception $e) {}
            }
        }

        // 3. Create the log message
        logActivity($pdo, 'User', "New Customer Registered: $full_name ($email)");
        // ---------------------------

        $success = 'Account created successfully! <a href="login.php" class="text-white fw-bold" style="text-decoration:underline;">Login here</a>';
    
    } else {
        $error = 'Could not create account. Please try again.';
    }
    
} catch (PDOException $e) {
    $error = 'Registration failed: ' . $e->getMessage();
}

// ... (Rest of your HTML code) ...
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - LaParfume</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Auth CSS -->
    <link rel="stylesheet" href="assets/login.css">
</head>
<body>
    
    <!-- Background Glow Blobs (Matches Login) -->
    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <!-- Top Banner -->
    <div class="top-banner">
        Flash Sale For Some Perfume And Free Express Delivery – OFF 50%! <a href="shop.php">ShopNow</a>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php" class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Parfume</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="signup.php">Sign Up</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center mt-3 mt-lg-0">
                    
                    <!-- Search Bar (Matches Login) -->
                    <div class="search-wrapper me-3 d-none d-lg-flex">
                        <input type="text" class="search-input" placeholder="What are you looking for?">
                        <button class="search-btn"><i class="fas fa-search"></i></button>
                    </div>

                    <!-- Search Icon (Mobile) -->
                    <button class="mobile-search-btn d-lg-none">
                        <i class="fas fa-search"></i>
                    </button>

                    <div class="nav-icons">
                        <button class="icon-btn" title="Wishlist">
                            <i class="far fa-heart"></i>
                        </button>
                        <button class="icon-btn" title="Cart">
                            <i class="fas fa-shopping-cart"></i>
                        </button>
                        <button class="icon-btn" title="Account">
                            <i class="far fa-user"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Signup Section -->
    <section class="signup-section">
        <!-- Using .signup-card directly matches the CSS flex centering better than Bootstrap grid -->
        <div class="signup-card">
            <h1>Create Account</h1>
            <p class="signup-subtitle">Sign up to start shopping</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <!-- Hide form if successful to prevent re-submission -->
            <?php if (!$success): ?>
            <form method="POST" action="" id="signupForm" autocomplete="off">
                <!-- Full Name -->
                <div class="mb-4">
                    <input type="text" name="full_name" class="form-control"
                           placeholder="Full Name *" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <input type="email" name="email" class="form-control"
                           placeholder="Email Address *" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>

                <!-- Phone -->
                <div class="mb-4">
                    <input type="tel" name="phone" class="form-control"
                           placeholder="Phone Number" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                </div>

                <!-- Address -->
                <div class="mb-4">
                    <textarea name="address" class="form-control" rows="2" 
                              placeholder="Delivery Address" style="resize: none;"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                </div>

                <!-- Password -->
                <div class="mb-4 password-wrapper">
                    <input type="password" name="password" id="password" class="form-control"
                           placeholder="Password *" required minlength="6">
                    <button type="button" class="password-toggle" onclick="togglePassword('password', 'icon1')">
                        <i class="far fa-eye" id="icon1"></i>
                    </button>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4 password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                           placeholder="Confirm Password *" required minlength="6">
                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'icon2')">
                        <i class="far fa-eye" id="icon2"></i>
                    </button>
                </div>
                        
                <button type="submit" class="btn btn-signup">
                    <i class="fas fa-user-plus me-2"></i> Create Account
                </button>
            </form>
            <?php endif; ?>
            
            <p class="login-text">
                Already have an account? <a href="login.php">Login here</a>
            </p>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <p>&copy; ITP - 7 LaParfume System.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>