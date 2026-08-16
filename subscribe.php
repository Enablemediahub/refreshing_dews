<?php
require_once 'includes/config.php';
require_once 'includes/db-connection.php';
require_once 'includes/functions.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Process subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    
    // Validate email
    if (empty($email)) {
        $_SESSION['subscribe_error'] = 'Please enter your email address.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['subscribe_error'] = 'Please enter a valid email address.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
        exit;
    }
    
    try {
        // Check if subscribers table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'subscribers'");
        if ($table_check->num_rows == 0) {
            $create_table = "CREATE TABLE IF NOT EXISTS `subscribers` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `email` varchar(255) NOT NULL,
                `name` varchar(100) DEFAULT NULL,
                `status` enum('active','unsubscribed','bounced') DEFAULT 'active',
                `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `unsubscribed_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
            $conn->query($create_table);
        }
        
        // Check if email exists
        $check_sql = "SELECT id, status FROM subscribers WHERE email = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $existing = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($existing) {
            if ($existing['status'] === 'unsubscribed') {
                // Reactivate subscription
                $update_sql = "UPDATE subscribers SET status = 'active', unsubscribed_at = NULL WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $existing['id']);
                $update_stmt->execute();
                $update_stmt->close();
                $_SESSION['subscribe_success'] = 'Welcome back! You have been resubscribed successfully.';
            } else {
                $_SESSION['subscribe_success'] = 'You are already subscribed to our newsletter!';
            }
        } else {
            // Insert new subscriber
            $insert_sql = "INSERT INTO subscribers (email, name, status) VALUES (?, ?, 'active')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ss", $email, $name);
            $insert_stmt->execute();
            $insert_stmt->close();
            $_SESSION['subscribe_success'] = getSetting('subscribe_success_message', 'Thank you for subscribing! You\'ll receive updates and new content directly in your inbox.');
            
            // Send welcome email (optional)
            $send_welcome = getSetting('subscribe_send_welcome_email', '0'); // Default to off
            if ($send_welcome == '1') {
                $site_title = getSetting('site_title', 'Painlesslyf');
                $welcome_subject = "Welcome to " . $site_title . " Newsletter!";
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "From: " . $site_title . " <" . getSetting('contact_email_display', 'newsletter@' . $_SERVER['HTTP_HOST']) . ">\r\n";
                
                $html_message = "
                <!DOCTYPE html>
                <html>
                <head><meta charset='UTF-8'><title>Welcome</title></head>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Welcome to " . htmlspecialchars($site_title) . "!</h2>
                    <p>Thank you for subscribing to our newsletter. You'll now receive updates about new blog posts, audio messages, and special content.</p>
                    <p>Best regards,<br>" . htmlspecialchars($site_title) . " Team</p>
                </body>
                </html>
                ";
                
                @mail($email, $welcome_subject, $html_message, $headers);
            }
        }
        
    } catch (Exception $e) {
        $_SESSION['subscribe_error'] = 'Sorry, there was an error. Please try again.';
        error_log("Subscription error: " . $e->getMessage());
    }
    
    // Redirect back to the page they came from
    $redirect_url = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header('Location: ' . $redirect_url);
    exit;
    
} else {
    // If someone visits subscribe.php directly
    header('Location: index.php');
    exit;
}
?>