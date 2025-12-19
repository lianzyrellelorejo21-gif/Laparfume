<?php
session_start();
require_once 'config/database.php';

$is_logged_in = isset($_SESSION['user_id']);
$message_sent = false;

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    // FORCE RECIPIENT TO ADMIN
    $recipient_type = 'Admin';
    $recipient_id = 0;

    if (!empty($name) && !empty($email) && !empty($message)) {
        try {
            // Insert into messages table
            $stmt = $pdo->prepare("INSERT INTO messages (sender_name, sender_email, recipient_type, recipient_id, subject, message) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $recipient_type, $recipient_id, $subject, $message]);

            $message_sent = true;
        } catch (PDOException $e) {
            // Handle error silently or log it
        }
    }
}

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
    <title>Contact Us - LaParfume</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    <link rel="stylesheet" href="assets/home.css">
    
    <style>
        /* Contact Specific Styles */
        .glass-panel {
            background: rgba(22, 22, 22, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 40px;
        }

        .contact-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }
        .contact-icon {
            width: 50px; height: 50px;
            background: rgba(29, 209, 161, 0.1);
            color: #1dd1a1;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            margin-right: 20px;
        }

        /* Input Styling */
        .form-control {
            background-color: #2b2b2b;
            border: 1px solid #444;
            color: #fff;
            padding: 12px;
        }
        .form-control:focus {
            background-color: #333;
            border-color: #1dd1a1;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(29, 209, 161, 0.2);
        }
        .form-label { color: #ccc; }
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
                    <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
                    <li class="nav-item"><a class="nav-link active" href="contact.php">Contact</a></li>
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
        <div class="row g-5">
            
            <div class="col-lg-5">
                <h5 class="text-teal-400 fw-bold mb-2">Get in Touch</h5>
                <h1 class="display-5 text-white fw-bold mb-4">Let's Chat</h1>
                <p class="text-muted mb-5">Have a question about a product, order, or just want to say hello? We'd love to hear from you.</p>

                <div class="contact-info-item">
                    <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div>
                        <h6 class="text-white mb-1">Our Location</h6>
                        <p class="text-muted mb-0">Misamis University, Oroquieta City, PH</p>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                    <div>
                        <h6 class="text-white mb-1">Email Us</h6>
                        <p class="text-muted mb-0">maiahrevah@laparfume.com</p>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                    <div>
                        <h6 class="text-white mb-1">Call Us</h6>
                        <p class="text-muted mb-0">+63 912 345 6789</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="glass-panel">
                    
                    <?php if ($message_sent): ?>
                        <div class="alert alert-success bg-dark border-success text-success d-flex align-items-center">
                            <i class="fas fa-check-circle me-2"></i> Message sent successfully! We will reply soon.
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Your Name</label>
                                <input type="text" name="name" class="form-control" required placeholder="Gogen">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" required placeholder="gogen@example.com">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control" required placeholder="Order Inquiry">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message</label>
                                <textarea name="message" class="form-control" rows="5" required placeholder="Type your message here..."></textarea>
                            </div>
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-success w-100 py-3 fw-bold" style="background-color: #1dd1a1; border: none; color: #000;">
                                    Send Message <i class="fas fa-paper-plane ms-2"></i>
                                </button>
                            </div>
                        </div>
                    </form>
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