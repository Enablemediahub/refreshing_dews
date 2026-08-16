<?php
require_once 'includes/config.php';
require_once 'includes/db-connection.php';
require_once 'includes/functions.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$email = isset($_GET['email']) ? trim($_GET['email']) : '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$message = '';
$error = '';

// If email and token are provided, process unsubscribe
if ($email && $token) {
    // Validate token
    $sql = "SELECT id, email, verification_token FROM subscribers WHERE email = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $subscriber = $result->fetch_assoc();
    $stmt->close();
    
    if ($subscriber && hash_equals($subscriber['verification_token'], $token)) {
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
        
        $message = "You have been successfully unsubscribed from our newsletter.";
    } else {
        $error = "Invalid or expired unsubscribe link.";
    }
} else {
    // If just visiting the page, show form
    $message = "";
}

$site_title = getSetting('site_title', 'Painlesslyf');
$primary_color = getSetting('primary_color', '#4a7c59');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unsubscribe - <?php echo htmlspecialchars($site_title); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #f0f2f5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .unsubscribe-container {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .unsubscribe-header {
            background: <?php echo $primary_color; ?>;
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .unsubscribe-header i {
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        .unsubscribe-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .unsubscribe-content {
            padding: 40px 30px;
        }
        
        .message-box {
            background: #d4edda;
            color: #155724;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        .info-box {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #17a2b8;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: <?php echo $primary_color; ?>;
        }
        
        .btn-unsubscribe {
            width: 100%;
            padding: 14px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-unsubscribe:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
        }
        
        .btn-home {
            display: inline-block;
            padding: 12px 24px;
            background: <?php echo $primary_color; ?>;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin-top: 20px;
            text-align: center;
        }
        
        .footer {
            text-align: center;
            padding: 20px 30px;
            background: #f8f9fa;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #e0e0e0;
        }
        
        @media (max-width: 768px) {
            .unsubscribe-header {
                padding: 30px 20px;
            }
            
            .unsubscribe-content {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="unsubscribe-container">
        <div class="unsubscribe-header">
            <i class="fas fa-envelope-open-text"></i>
            <h1>Unsubscribe</h1>
            <p>Manage your newsletter subscription</p>
        </div>
        
        <div class="unsubscribe-content">
            <?php if ($message): ?>
                <div class="message-box">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
                <div style="text-align: center;">
                    <a href="index.php" class="btn-home">Return to Homepage</a>
                </div>
            <?php elseif ($error): ?>
                <div class="error-box">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
                <div style="text-align: center;">
                    <a href="index.php" class="btn-home">Return to Homepage</a>
                </div>
            <?php else: ?>
                <div class="info-box">
                    <i class="fas fa-info-circle"></i> Enter your email address to unsubscribe from our newsletter.
                </div>
                
                <form method="POST" action="unsubscribe-process.php">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="your@email.com">
                    </div>
                    <button type="submit" class="btn-unsubscribe" onclick="return confirm('Are you sure you want to unsubscribe? You will no longer receive updates from us.')">
                        <i class="fas fa-times-circle"></i> Unsubscribe
                    </button>
                </form>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="index.php" style="color: <?php echo $primary_color; ?>; text-decoration: none;">← Back to Homepage</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_title); ?>. All rights reserved.</p>
            <p style="font-size: 12px; opacity: 0.85; margin-top: 6px;">Designed and Developed by <strong>DALE QUIST</strong> [Enable Technologies]</p>
        </div>
    </div>
</body>
</html>