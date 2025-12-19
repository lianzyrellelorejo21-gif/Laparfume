<?php
session_start();
require_once 'config/database.php';

// Helper function to sanitize input
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

$error = '';
$success = '';
$step = 'email'; // email, verify, reset, complete

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['step']) && $_POST['step'] == 'email') {
        // Step 1: Check if email exists
        $email = sanitize($_POST['email'] ?? '');
        
        if (empty($email)) {
            $error = 'Please enter your email address';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            // Check customers, sellers, admins tables
            $stmt = $pdo->prepare("SELECT customer_id as id, email, 'customer' as user_type FROM customers WHERE email = ?
                                   UNION
                                   SELECT seller_id as id, email, 'seller' as user_type FROM sellers WHERE email = ?
                                   UNION
                                   SELECT admin_id as id, email, 'admin' as user_type FROM admins WHERE email = ?");
            $stmt->execute([$email, $email, $email]);
            $user = $stmt->fetch();
            
            if ($user) {
                $reset_token = bin2hex(random_bytes(32));
                $verification_code = strtoupper(substr($reset_token, 0, 6)); 
                $reset_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_token'] = $reset_token;
                $_SESSION['verify_code'] = $verification_code;
                $_SESSION['reset_user_type'] = $user['user_type'];
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_expiry'] = $reset_expiry;
                
                // Demo purposes: showing code in success message
                $success = 'Verification code sent! (For Demo: ' . $verification_code . ')';
                $step = 'verify';
            } else {
                $error = 'No account found with this email address';
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == 'verify') {
        // Step 2: Verify code
        $token = sanitize($_POST['token'] ?? '');
        
        if (empty($token)) {
            $error = 'Please enter the verification code';
        } elseif (!isset($_SESSION['verify_code']) || strtoupper($token) !== $_SESSION['verify_code']) {
            $error = 'Invalid verification code';
        } elseif (strtotime($_SESSION['reset_expiry']) < time()) {
            $error = 'Verification code has expired';
            unset($_SESSION['reset_token']);
        } else {
            $success = 'Code verified! Create your new password.';
            $step = 'reset';
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == 'reset') {
        // Step 3: Reset Password
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirm_password)) {
            $error = 'Please enter and confirm your new password';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $user_type = $_SESSION['reset_user_type'];
            $user_id = $_SESSION['reset_user_id'];
            
            $table = $user_type . 's'; 
            $id_field = $user_type . '_id';
            
            $stmt = $pdo->prepare("UPDATE $table SET password = ? WHERE $id_field = ?");
            if ($stmt->execute([$hashed_password, $user_id])) {
                session_unset();
                session_destroy();
                session_start();
                $success = 'Password reset successful! You can now login.';
                $step = 'complete';
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        }
    }
}

// Preserve step logic if error
if (isset($_POST['step']) && !empty($error)) {
    $step = $_POST['step'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - LaParfume</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="assets/forgot_password.css">
</head>
<body>

    <div class="top-banner">
        Flash Sale For Some Perfume And Free Express Delivery – OFF 50%! <a href="shop.php">ShopNow</a>
    </div>

    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php" class="fw-bold text-white">LA<span class="text-teal-400" style="color: #1dd1a1;">Parfume</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="signup.php">Sign Up</a></li>
                </ul>
                <div class="d-flex text-white">
                     <i class="fas fa-search me-3"></i>
                     <i class="far fa-heart me-3"></i>
                     <i class="fas fa-shopping-cart me-3"></i>
                     <i class="far fa-user"></i>
                </div>
            </div>
        </div>
    </nav>

    <section class="login-section">
        <div class="container">
            <div class="login-card">
                <h1>
                    <?php if ($step == 'complete'): ?>
                        Reset Complete
                    <?php else: ?>
                        Forgot Password
                    <?php endif; ?>
                </h1>
                <p class="login-subtitle">
                    <?php if ($step == 'email'): ?>
                        Enter your email details below
                    <?php elseif ($step == 'verify'): ?>
                        Enter the verification code sent to your email
                    <?php elseif ($step == 'reset'): ?>
                        Create your new password
                    <?php else: ?>
                        Your password has been updated
                    <?php endif; ?>
                </p>

                <?php if ($step != 'complete'): ?>
                <div class="step-indicator">
                    <div class="step-line"></div>
                    
                    <div class="step-item">
                        <div class="step-circle <?php echo ($step == 'email' || $step == 'verify' || $step == 'reset') ? 'active' : ''; ?>">
                            <?php echo ($step != 'email') ? '<i class="fas fa-check"></i>' : '1'; ?>
                        </div>
                        <div class="step-label <?php echo $step == 'email' ? 'active' : ''; ?>">Email</div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-circle <?php echo ($step == 'verify' || $step == 'reset') ? 'active' : ''; ?>">
                            <?php echo ($step == 'reset') ? '<i class="fas fa-check"></i>' : '2'; ?>
                        </div>
                        <div class="step-label <?php echo $step == 'verify' ? 'active' : ''; ?>">Verify</div>
                    </div>
                    
                    <div class="step-item">
                        <div class="step-circle <?php echo ($step == 'reset') ? 'active' : ''; ?>">3</div>
                        <div class="step-label <?php echo $step == 'reset' ? 'active' : ''; ?>">Reset</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($step == 'email'): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="step" value="email">
                        <div class="mb-4 text-start">
                            <input type="email" name="email" class="form-control" placeholder="Email or Phone Number" required>
                        </div>
                        <button type="submit" class="btn btn-login">
                            Send Code <i class="fas fa-arrow-right ms-2"></i>
                        </button>
                    </form>

                <?php elseif ($step == 'verify'): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="step" value="verify">
                        <div class="mb-4 text-start">
                            <input type="text" name="token" class="form-control token-input" placeholder="CODE" maxlength="8" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-login">
                            Verify Code
                        </button>
                        <div class="mt-3">
                            <small class="text-muted">Didn't receive code? <a href="forgot_password.php" class="text-light">Try again</a></small>
                        </div>
                    </form>

                <?php elseif ($step == 'reset'): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="step" value="reset">
                        <div class="mb-3 text-start password-wrapper">
                            <input type="password" name="password" id="new_password" class="form-control" placeholder="New Password" required minlength="6">
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password', 'icon1')">
                                <i class="far fa-eye" id="icon1"></i>
                            </button>
                        </div>
                        <div class="mb-4 text-start password-wrapper">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password" required minlength="6">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password', 'icon2')">
                                <i class="far fa-eye" id="icon2"></i>
                            </button>
                        </div>
                        <button type="submit" class="btn btn-login">
                            Reset Password
                        </button>
                    </form>

                <?php else: ?>
                    <div class="mb-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 60px;"></i>
                    </div>
                    <a href="login.php" class="btn btn-login">
                        Go to Login
                    </a>
                <?php endif; ?>
                
                <p class="signup-text">
                    Remember your password? <a href="login.php">Log In</a>
                </p>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; ITP - 7 LaParfume System.</p>
        </div>
    </footer>

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