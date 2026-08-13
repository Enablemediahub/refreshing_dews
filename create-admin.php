<?php
require_once 'includes/config.php';
require_once 'includes/db-connection.php';

echo "<h2>Create Admin User</h2>";

if (isset($conn) && !$conn->connect_error) {
    // Check if admin already exists
    $check = $conn->query("SELECT id FROM users WHERE username = 'admin'");
    
    if ($check->num_rows > 0) {
        echo "<p style='color: orange'>Admin user already exists!</p>";
    } else {
        // Create admin user
        $username = 'admin';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $email = 'admin@refreshingdews.com';
        
        $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $password, $email);
        
        if ($stmt->execute()) {
            echo "<p style='color: green'>✓ Admin user created successfully!</p>";
            echo "<p>Username: admin</p>";
            echo "<p>Password: admin123</p>";
            echo "<p>Email: admin@refreshingdews.com</p>";
        } else {
            echo "<p style='color: red'>✗ Failed to create admin user: " . $stmt->error . "</p>";
        }
    }
} else {
    echo "<p style='color: red'>✗ Database connection failed!</p>";
}
?>