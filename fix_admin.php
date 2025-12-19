<?php
require_once 'config/database.php';

// The password you want to use
$password_plain = 'admin123';

// Generate the REAL hash
$password_hash = password_hash($password_plain, PASSWORD_DEFAULT);

$email = 'admin@laparfume.com';

try {
    // Check if the admin exists
    $stmt = $pdo->prepare("SELECT admin_id FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    $exists = $stmt->fetch();

    if ($exists) {
        // Update existing admin with the correct hash
        $sql = "UPDATE admins SET password = ?, is_active = 1, role = 'Super Admin' WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$password_hash, $email]);
        echo "<h1 style='color: green;'>Success!</h1>";
        echo "<p>Admin password updated successfully.</p>";
    } else {
        // Create new admin if not exists
        $sql = "INSERT INTO admins (full_name, email, password, role, is_active, date_created) VALUES (?, ?, ?, 'Super Admin', 1, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['Super Admin', $email, $password_hash]);
        echo "<h1 style='color: green;'>Success!</h1>";
        echo "<p>New Admin account created successfully.</p>";
    }

    echo "<p><strong>Email:</strong> $email<br><strong>Password:</strong> $password_plain</p>";
    echo "<br><a href='login.php'>Go to Login Page</a>";

} catch (PDOException $e) {
    echo "<h1 style='color: red;'>Error</h1>";
    echo "Database error: " . $e->getMessage();
}
?>