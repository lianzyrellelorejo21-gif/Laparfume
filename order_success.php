<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    header('Location: index.php');
    exit();
}
$order_id = $_GET['order_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Placed - LaParfume</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/login.css">
    
    <style>
        body { background-color: #000; color: #fff; font-family: 'Poppins', sans-serif; overflow: hidden; }
        
        .success-card {
            background: rgba(22, 22, 22, 0.8);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 50px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            position: relative;
            z-index: 10;
            animation: popIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        @keyframes popIn {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .icon-circle {
            width: 100px; height: 100px;
            background: linear-gradient(135deg, rgba(29, 209, 161, 0.2), rgba(29, 209, 161, 0.05));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 30px auto;
            border: 1px solid rgba(29, 209, 161, 0.3);
            box-shadow: 0 0 30px rgba(29, 209, 161, 0.2);
        }

        .order-number {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block;
            margin: 20px 0;
            font-family: monospace;
            font-size: 1.2rem;
            letter-spacing: 2px;
            color: #1dd1a1;
            border: 1px dashed rgba(29, 209, 161, 0.3);
        }

        .btn-view-order {
            background: #1dd1a1; color: #000; font-weight: 700; border: none;
            padding: 12px 30px; border-radius: 10px; transition: 0.3s;
        }
        .btn-view-order:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(29, 209, 161, 0.3); background: #1dd1a1; color: #000; }

        .btn-continue {
            border: 1px solid rgba(255, 255, 255, 0.2); color: #aaa;
            padding: 12px 30px; border-radius: 10px; transition: 0.3s;
        }
        .btn-continue:hover { border-color: #fff; color: #fff; background: transparent; }

        .glow-blob { position: absolute; width: 600px; height: 600px; background: radial-gradient(circle, rgba(29,209,161,0.15) 0%, rgba(0,0,0,0) 70%); border-radius: 50%; pointer-events: none; z-index: 1; }
        .blob-1 { top: -200px; left: -200px; } .blob-2 { bottom: -200px; right: -200px; }

        /* Confetti Effect */
        .confetti { position: absolute; width: 10px; height: 10px; background-color: #1dd1a1; animation: fall linear forwards; z-index: 5; }
        @keyframes fall { to { transform: translateY(100vh) rotate(720deg); } }
    </style>
</head>
<body>

    <div class="glow-blob blob-1"></div>
    <div class="glow-blob blob-2"></div>

    <div class="d-flex align-items-center justify-content-center min-vh-100 px-3">
        <div class="success-card">
            <div class="icon-circle">
                <i class="fas fa-check fa-3x" style="color: #1dd1a1;"></i>
            </div>
            
            <h1 class="fw-bold text-white mb-2">Order Confirmed!</h1>
            <p class="text-muted">Thank you for shopping with LaParfume.</p>
            
            <div class="order-number">Order #<?php echo htmlspecialchars($order_id); ?></div>
            
            <p class="text-white-50 small mb-4">You will receive an email confirmation shortly.<br>You can track your order status in your account.</p>
            
            <div class="d-flex flex-column gap-3">
                <a href="customer/account.php" class="btn btn-view-order w-100">
                    View Order <i class="fas fa-arrow-right ms-2"></i>
                </a>
                <a href="shop.php" class="btn btn-continue w-100">Continue Shopping</a>
            </div>
        </div>
    </div>

    <script>
        function createConfetti() {
            const colors = ['#1dd1a1', '#ff6b6b', '#54a0ff', '#feca57'];
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.classList.add('confetti');
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDuration = Math.random() * 3 + 2 + 's';
                confetti.style.opacity = Math.random();
                document.body.appendChild(confetti);
                
                // Remove confetti after animation
                setTimeout(() => { confetti.remove(); }, 5000);
            }
        }
        createConfetti();
    </script>

</body>
</html>