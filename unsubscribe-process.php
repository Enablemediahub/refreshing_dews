<?php
require_once 'includes/config.php';
require_once 'includes/db-connection.php';
require_once 'includes/functions.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['unsubscribe_error'] = 'Please enter a valid email address.';
        header('Location: unsubscribe.php');
        exit;
    }
    
    // Check if email exists
    $sql = "SELECT id, email, verification_token FROM subscribers WHERE email = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriber = $result->fetch_assoc();
    $stmt->close();
    
    if ($subscriber) {
        // Update status to unsubscribed
        $update_sql = "UPDATE subscribers SET status = 'unsubscribed', unsubscribed_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $subscriber['id']);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Log the unsubscription
        $log_sql = "INSERT INTO newsletter_logs (subscriber_id, email, type, ip_address) VALUES (?, ?, 'unsubscribe', ?)";
        $log_stmt = $conn->prepare($log_sql);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $log_stmt->bind_param("iss", $subscriber['id'], $email, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();
        
        $_SESSION['unsubscribe_success'] = "You have been successfully unsubscribed from our newsletter.";
    } else {
        $_SESSION['unsubscribe_error'] = "Email address not found or already unsubscribed.";
    }
    
    header('Location: unsubscribe.php');
    exit;
} else {
    header('Location: unsubscribe.php');
    exit;
}
?>