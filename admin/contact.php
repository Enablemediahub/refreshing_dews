<?php
require_once '../includes/config.php';
require_once '../includes/db-connection.php';
require_once '../includes/functions.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

// Get current admin info
$admin_id = $_SESSION['admin_id'] ?? 0;
$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$message_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$reply_message = '';
$reply_error = '';

// Get filter values early so handlers can use them
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $message_id = intval($_POST['message_id']);
    $reply_subject = trim($_POST['reply_subject']);
    $reply_body = trim($_POST['reply_body']);
    $admin_notes = trim($_POST['admin_notes']);
    
    if (empty($reply_subject) || empty($reply_body)) {
        $reply_error = 'Please fill in both subject and message fields.';
    } else {
        // Get original message details
        $sql = "SELECT first_name, last_name, email FROM contact_messages WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $message_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $message = $result->fetch_assoc();
        $stmt->close();
        
        if ($message) {
            $to_email = $message['email'];
            $to_name = trim($message['first_name'] . ' ' . $message['last_name']);
            
            // Send email using PHP mail() function
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . getSetting('site_title', 'Painlesslyf') . " <" . getSetting('contact_email_display', 'noreply@' . $_SERVER['HTTP_HOST']) . ">\r\n";
            $headers .= "Reply-To: " . getSetting('contact_email_display', 'contact@' . $_SERVER['HTTP_HOST']) . "\r\n";
            
            $html_message = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <title>" . htmlspecialchars($reply_subject) . "</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
                    .email-container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                    .email-header { background: #4a7c59; padding: 30px 20px; text-align: center; }
                    .email-header h2 { margin: 0; color: white; font-size: 24px; }
                    .email-content { padding: 30px; }
                    .greeting { font-size: 18px; margin-bottom: 20px; }
                    .reply-box { background: #f9f9f9; padding: 20px; border-left: 4px solid #4a7c59; margin: 20px 0; border-radius: 8px; }
                    .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #888; border-top: 1px solid #e0e0e0; }
                </style>
            </head>
            <body>
                <div class='email-container'>
                    <div class='email-header'>
                        <h2>" . htmlspecialchars(getSetting('site_title', 'Painlesslyf')) . "</h2>
                    </div>
                    <div class='email-content'>
                        <div class='greeting'>
                            <strong>Hello " . htmlspecialchars($to_name) . ",</strong>
                        </div>
                        <div class='reply-box'>
                            " . nl2br(htmlspecialchars($reply_body)) . "
                        </div>
                        <p>Best regards,<br>
                        <strong>" . htmlspecialchars(getSetting('site_title', 'Painlesslyf')) . "</strong></p>
                    </div>
                    <div class='footer'>
                        <p>This email was sent in response to your message. If you have any further questions, feel free to reply to this email.</p>
                        <p>&copy; " . date('Y') . " " . htmlspecialchars(getSetting('site_title', 'Painlesslyf')) . ". All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            if (mail($to_email, $reply_subject, $html_message, $headers)) {
                // Update message status
                $update_sql = "UPDATE contact_messages SET status = 'replied', reply_sent = 1, replied_at = NOW(), admin_notes = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $admin_notes, $message_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                $reply_message = 'Reply sent successfully!';
            } else {
                $reply_error = 'Failed to send email. Please check your mail server configuration.';
            }
        } else {
            $reply_error = 'Message not found.';
        }
    }
}

// Handle status update
if (isset($_POST['update_status'])) {
    $message_id = intval($_POST['message_id']);
    $new_status = $_POST['status'];
    $valid_statuses = ['unread', 'read', 'replied'];
    
    if (in_array($new_status, $valid_statuses)) {
        $sql = "UPDATE contact_messages SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $message_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle delete
if (isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id']);
    $sql = "DELETE FROM contact_messages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $stmt->close();
    // Redirect to prevent form resubmission and preserve filters
    $redirect = 'contact.php';
    $redirect_params = [];
    if ($status_filter) $redirect_params[] = "status=" . urlencode($status_filter);
    if ($search) $redirect_params[] = "search=" . urlencode($search);
    if (!empty($redirect_params)) $redirect .= "?" . implode('&', $redirect_params);
    header("Location: " . $redirect);
    exit();
}

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_messages']) && is_array($_POST['selected_messages'])) {
    $selected = array_values(array_unique(array_filter(array_map('intval', $_POST['selected_messages']))));

    if (!empty($selected)) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $types = str_repeat('i', count($selected));
        $sql = "DELETE FROM contact_messages WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$selected);
        $stmt->execute();
        $stmt->close();
    }

    // Redirect to prevent form resubmission and preserve filters
    $redirect = 'contact.php';
    $redirect_params = [];
    if ($status_filter) $redirect_params[] = "status=" . urlencode($status_filter);
    if ($search) $redirect_params[] = "search=" . urlencode($search);
    if (!empty($redirect_params)) $redirect .= "?" . implode('&', $redirect_params);
    header("Location: " . $redirect);
    exit();
}

// Get single message for viewing/reply
$current_message = null;
if ($action === 'view' && $message_id > 0) {
    $sql = "SELECT * FROM contact_messages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $message_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_message = $result->fetch_assoc();
    $stmt->close();
    
    // Mark as read if currently unread
    if ($current_message && $current_message['status'] === 'unread') {
        $update_sql = "UPDATE contact_messages SET status = 'read' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $message_id);
        $update_stmt->execute();
        $update_stmt->close();
        $current_message['status'] = 'read';
    }
}

// Get statistics with proper defaults
$stats_sql = "SELECT 
                COALESCE(COUNT(*), 0) as total,
                COALESCE(SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END), 0) as unread,
                COALESCE(SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END), 0) as read_count,
                COALESCE(SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END), 0) as replied,
                COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END), 0) as today
              FROM contact_messages";
$stats_result = $conn->query($stats_sql);
if ($stats_result && $stats_result->num_rows > 0) {
    $stats = $stats_result->fetch_assoc();
} else {
    $stats = [
        'total' => 0,
        'unread' => 0,
        'read_count' => 0,
        'replied' => 0,
        'today' => 0
    ];
}

// Get messages list with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filter variables already defined above for handlers

$where_conditions = [];
$params = [];
$types = "";

if ($status_filter && in_array($status_filter, ['unread', 'read', 'replied'])) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sssss";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_sql = "SELECT COUNT(*) as total FROM contact_messages $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_messages = $count_result->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total_pages = $total_messages > 0 ? ceil($total_messages / $per_page) : 1;

// Get messages
$sql = "SELECT * FROM contact_messages $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$messages = $stmt->get_result();
$stmt->close();

// Helper function for date formatting
if (!function_exists('formatDate')) {
    function formatDate($date) {
        return $date ? date('M j, Y g:i A', strtotime($date)) : 'Unknown';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Contact Messages - Admin Panel</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <style>
        /* Copy all styles from dashboard.php here */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        html, body {
            height: 100%;
            overflow-x: hidden;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f9;
            color: #333;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            position: relative;
        }
        
        /* Sidebar Styles */
        .admin-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2c3e50 0%, #1e2b37 100%);
            color: white;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            flex-shrink: 0;
            position: sticky;
            top: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
        }
        
        @media (max-width: 1024px) {
            .admin-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                transform: translateX(-100%);
                z-index: 2000;
                width: 280px;
                height: 100vh;
            }
            
            .admin-sidebar.sidebar-open {
                transform: translateX(0);
            }
        }
        
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header img {
            max-width: 150px;
            margin-bottom: 15px;
            background: white;
            padding: 10px;
            border-radius: 10px;
        }
        
        .sidebar-header h3 {
            font-size: 18px;
            font-weight: 600;
        }
        
        .sidebar-menu {
            padding: 20px 0 40px 0;
        }
        
        .sidebar-menu-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .sidebar-menu-item i {
            width: 20px;
        }
        
        .sidebar-menu-item:hover,
        .sidebar-menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #4a7c59;
        }
        
        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 15px 20px;
        }
        
        .sidebar-menu-label {
            padding: 10px 25px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1500;
            background: #4a7c59;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            font-size: 20px;
        }
        
        @media (max-width: 1024px) {
            .mobile-menu-toggle {
                display: flex;
            }
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1999;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        .admin-main {
            flex: 1;
            min-height: 100vh;
            padding: 30px;
            background: #f4f6f9;
        }
        
        @media (max-width: 1024px) {
            .admin-main {
                padding: 80px 20px 20px 20px;
            }
        }
        
        .top-nav {
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .top-nav-title h1 {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        
        .top-nav-title p {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .top-nav-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
        }
        
        .user-role {
            font-size: 12px;
            color: #666;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #4a7c59, #2c4a3b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #4a7c59, #2c4a3b);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(74, 124, 89, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .stat-icon i {
            font-size: 24px;
            color: #4a7c59;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 13px;
        }
        
        .filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .form-control {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .btn-primary, .btn-secondary {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #4a7c59;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .messages-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            overflow-x: auto;
        }
        
        .messages-table table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .messages-table th,
        .messages-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .messages-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-unread {
            background: #dc3545;
            color: white;
        }
        
        .status-read {
            background: #ffc107;
            color: #333;
        }
        
        .status-replied {
            background: #28a745;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .btn-icon {
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border: none;
            cursor: pointer;
        }
        
        .btn-view {
            background: #4a7c59;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
        }
        
        .pagination .active {
            background: #4a7c59;
            color: white;
            border-color: #4a7c59;
        }
        
        .message-detail {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .message-header {
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .reply-form {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .checkbox-column {
            width: 30px;
        }
        
        .bulk-actions {
            margin-bottom: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="admin-wrapper">
        <?php $current_page = basename(__FILE__); include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="top-nav">
                <div class="top-nav-title">
                    <h1>Contact Messages</h1>
                    <p>View and manage messages from your website visitors</p>
                </div>
                
                <div class="top-nav-user">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($admin_username); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </div>
            
            <?php if ($reply_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($reply_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($reply_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($reply_error); ?>
            </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <form method="POST" action="contact.php" id="bulkDeleteForm" style="display:none;" class="standalone-bulk">
                    <input type="hidden" name="bulk_delete" value="1">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <div id="bulkInputs"></div>
                </form>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total'] ?? 0); ?></div>
                    <div class="stat-label">Total Messages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value" style="color: #dc3545;"><?php echo number_format($stats['unread'] ?? 0); ?></div>
                    <div class="stat-label">Unread</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value" style="color: #28a745;"><?php echo number_format($stats['replied'] ?? 0); ?></div>
                    <div class="stat-label">Replied</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['today'] ?? 0); ?></div>
                    <div class="stat-label">Today</div>
                </div>
            </div>
            
            <?php if ($action === 'view' && $current_message): ?>
            <!-- View Single Message -->
            <a href="contact.php" class="btn-secondary" style="display: inline-block; margin-bottom: 20px;">
                <i class="fas fa-arrow-left"></i> Back to Messages
            </a>
            
            <div class="message-detail">
                <div class="message-header">
                    <h2><?php echo htmlspecialchars($current_message['subject'] ?: 'No Subject'); ?></h2>
                    <p>
                        <strong>From:</strong> <?php echo htmlspecialchars($current_message['first_name'] . ' ' . $current_message['last_name']); ?><br>
                        <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($current_message['email']); ?>"><?php echo htmlspecialchars($current_message['email']); ?></a><br>
                        <strong>Date:</strong> <?php echo formatDate($current_message['created_at']); ?><br>
                        <strong>Status:</strong> 
                        <span class="status-badge status-<?php echo $current_message['status']; ?>">
                            <?php echo ucfirst($current_message['status']); ?>
                        </span>
                    </p>
                </div>
                
                <div class="message-body">
                    <h3>Message:</h3>
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 10px;">
                        <?php echo nl2br(htmlspecialchars($current_message['message'])); ?>
                    </div>
                </div>
                
                <?php if ($current_message['admin_notes']): ?>
                <div style="margin-top: 20px;">
                    <h3>Admin Notes:</h3>
                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <?php echo nl2br(htmlspecialchars($current_message['admin_notes'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Reply Form -->
                <div class="reply-form">
                    <h3>Send Reply</h3>
                    <form method="POST" action="contact.php?action=view&id=<?php echo $current_message['id']; ?>">
                        <input type="hidden" name="message_id" value="<?php echo $current_message['id']; ?>">
                        
                        <div class="form-group">
                            <label>Subject:</label>
                            <input type="text" name="reply_subject" value="Re: <?php echo htmlspecialchars($current_message['subject'] ?: 'Your Message'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Message:</label>
                            <textarea name="reply_body" rows="8" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Admin Notes (internal only):</label>
                            <textarea name="admin_notes" rows="3" placeholder="Add notes about this conversation..."></textarea>
                        </div>
                        
                        <button type="submit" name="send_reply" class="btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Reply
                        </button>
                    </form>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Messages List -->
            <div class="filters">
                <form method="GET" action="contact.php" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                    <select name="status" class="form-control" style="width: auto;">
                        <option value="">All Status</option>
                        <option value="unread" <?php echo $status_filter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                        <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                        <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                    </select>
                    
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or message..." 
                           value="<?php echo htmlspecialchars($search); ?>" style="min-width: 250px;">
                    
                    <button type="submit" class="btn-primary">Filter</button>
                    <a href="contact.php" class="btn-secondary">Reset</a>
                </form>
            </div>
            
            <div class="bulk-actions">
                <button type="button" id="bulkDeleteBtn" class="btn-danger">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            </div>
            
            <div class="messages-table">
                <table>
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>From</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($messages && $messages->num_rows > 0): ?>
                                <?php while ($msg = $messages->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_messages[]" value="<?php echo $msg['id']; ?>" class="message-checkbox">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($msg['email']); ?></td>
                                    <td><?php echo htmlspecialchars($msg['subject'] ?: 'No Subject'); ?></td>
                                    <td><?php echo formatDate($msg['created_at']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $msg['status']; ?>">
                                            <?php echo ucfirst($msg['status']); ?>
                                        </span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="contact.php?action=view&id=<?php echo $msg['id']; ?>" class="btn-icon btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <form method="POST" action="contact.php" style="display: inline;" onsubmit="return confirm('Delete this message?');">
                                            <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                            <button type="submit" name="delete_message" value="1" class="btn-icon btn-delete">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 60px;">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>No messages found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php
                    $query_params = $_GET;
                    $query_params['page'] = $i;
                    $query_string = http_build_query($query_params);
                    ?>
                    <a href="contact.php?<?php echo $query_string; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        var selectAllCheckbox = document.getElementById("selectAll");
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener("change", function() {
                var checkboxes = document.querySelectorAll(".message-checkbox");
                checkboxes.forEach(function(cb) {
                    cb.checked = selectAllCheckbox.checked;
                });
            });
        }

        var bulkDeleteButton = document.getElementById("bulkDeleteBtn");
        if (bulkDeleteButton) {
            bulkDeleteButton.addEventListener("click", function() {
                var checkboxes = document.querySelectorAll(".message-checkbox:checked");
                if (checkboxes.length === 0) {
                    alert("Please select at least one message.");
                    return false;
                }
                if (!confirm("Are you sure you want to delete selected messages?")) {
                    return false;
                }

                var form = document.getElementById("bulkDeleteForm");
                if (!form) {
                    form = document.createElement("form");
                    form.method = "POST";
                    form.action = "contact.php";
                    form.id = "bulkDeleteForm";
                    form.style.display = "none";
                    document.body.appendChild(form);
                }

                var container = document.getElementById("bulkInputs") || document.createElement("div");
                if (!container.id) {
                    container.id = "bulkInputs";
                    form.appendChild(container);
                }

                container.innerHTML = "";
                checkboxes.forEach(function(cb) {
                    var input = document.createElement("input");
                    input.type = "hidden";
                    input.name = "selected_messages[]";
                    input.value = cb.value;
                    container.appendChild(input);
                });
                form.submit();
            });
        }
    
        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const adminSidebar = document.getElementById('adminSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            adminSidebar.classList.toggle('sidebar-open');
            sidebarOverlay.classList.toggle('active');
            
            const icon = mobileMenuToggle.querySelector('i');
            if (adminSidebar.classList.contains('sidebar-open')) {
                icon.className = 'fas fa-times';
                document.body.style.overflow = 'hidden';
            } else {
                icon.className = 'fas fa-bars';
                document.body.style.overflow = '';
            }
        }
        
        function closeSidebar() {
            adminSidebar.classList.remove('sidebar-open');
            sidebarOverlay.classList.remove('active');
            const icon = mobileMenuToggle.querySelector('i');
            icon.className = 'fas fa-bars';
            document.body.style.overflow = '';
        }
        
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', toggleSidebar);
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', closeSidebar);
        }
        
        // Close sidebar on link click (mobile)
        const sidebarLinks = document.querySelectorAll('.sidebar-menu-item');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (window.innerWidth <= 1024 && !this.href.includes('logout')) {
                    setTimeout(closeSidebar, 150);
                }
            });
        });
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 1024 && adminSidebar.classList.contains('sidebar-open')) {
                    closeSidebar();
                }
            }, 250);
        });
        
        // Select all functionality
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.message-checkbox');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                });
            });
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.style.display = 'none';
                    }
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>