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
$subscriber_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$error = '';

// Function to send email with error handling
function sendEmailWithErrorHandling($to, $subject, $message, $from_name = '', $from_email = '') {
    global $error;
    
    if (empty($from_name)) {
        $from_name = getSetting('site_title', 'Painlesslyf');
    }
    if (empty($from_email)) {
        $from_email = getSetting('contact_email_display', 'noreply@' . $_SERVER['HTTP_HOST']);
    }
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $from_name . " <" . $from_email . ">\r\n";
    
    // For XAMPP local testing, we'll use a log file instead of actually sending
    if (strpos($_SERVER['SERVER_SOFTWARE'] ?? '', 'XAMPP') !== false || 
        strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) {
        // Log email to file for testing
        $log_dir = '../logs/';
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        $log_file = $log_dir . 'email_log_' . date('Y-m-d') . '.txt';
        $log_content = "[" . date('Y-m-d H:i:s') . "]\n";
        $log_content .= "To: $to\n";
        $log_content .= "Subject: $subject\n";
        $log_content .= "From: $from_name <$from_email>\n";
        $log_content .= "Message:\n$message\n";
        $log_content .= str_repeat("-", 50) . "\n\n";
        
        file_put_contents($log_file, $log_content, FILE_APPEND);
        
        // Show info message
        $_SESSION['email_debug'] = "Email would have been sent to $to (logged in logs/email_log.txt)";
        return true;
    }
    
    // For live server, attempt actual email send
    if (@mail($to, $subject, $message, $headers)) {
        return true;
    } else {
        return false;
    }
}

// Handle export subscribers
if (isset($_POST['export_csv'])) {
    $status_filter = isset($_POST['export_status']) ? $_POST['export_status'] : '';
    
    $sql = "SELECT email, name, status, subscribed_at FROM subscribers";
    if ($status_filter && in_array($status_filter, ['active', 'unsubscribed', 'bounced'])) {
        $sql .= " WHERE status = '" . $conn->real_escape_string($status_filter) . "'";
    }
    $sql .= " ORDER BY subscribed_at DESC";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $filename = "subscribers_" . date('Y-m-d') . ".csv";
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Email', 'Name', 'Status', 'Subscribed Date']);
        
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['email'],
                $row['name'] ?? '',
                $row['status'],
                date('Y-m-d H:i:s', strtotime($row['subscribed_at']))
            ]);
        }
        
        fclose($output);
        exit;
    } else {
        $error = 'No subscribers to export.';
    }
}

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_subscribers'])) {
    $selected = $_POST['selected_subscribers'];
    $placeholders = implode(',', array_fill(0, count($selected), '?'));
    $types = str_repeat('i', count($selected));
    $sql = "DELETE FROM subscribers WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$selected);
    $stmt->execute();
    $stmt->close();
    $message = count($selected) . ' subscriber(s) deleted successfully.';
}

// Handle single delete
if (isset($_POST['delete_subscriber'])) {
    $subscriber_id = intval($_POST['subscriber_id']);
    $sql = "DELETE FROM subscribers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $subscriber_id);
    $stmt->execute();
    $stmt->close();
    $message = 'Subscriber deleted successfully.';
}

// Handle status update
if (isset($_POST['update_status'])) {
    $subscriber_id = intval($_POST['subscriber_id']);
    $new_status = $_POST['status'];
    $valid_statuses = ['active', 'unsubscribed', 'bounced'];
    
    if (in_array($new_status, $valid_statuses)) {
        if ($new_status == 'unsubscribed') {
            $sql = "UPDATE subscribers SET status = ?, unsubscribed_at = NOW() WHERE id = ?";
        } else {
            $sql = "UPDATE subscribers SET status = ?, unsubscribed_at = NULL WHERE id = ?";
        }
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $subscriber_id);
        $stmt->execute();
        $stmt->close();
        $message = 'Subscriber status updated successfully.';
    }
}

// Handle send test email
if (isset($_POST['send_test_email'])) {
    $test_email = trim($_POST['test_email']);
    $test_subject = trim($_POST['test_subject']);
    $test_message = trim($_POST['test_message']);
    
    if (empty($test_email) || empty($test_subject) || empty($test_message)) {
        $error = 'Please fill in all fields for test email.';
    } elseif (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $site_title = getSetting('site_title', 'Painlesslyf');
        
        $html_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>" . htmlspecialchars($test_subject) . "</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { background: #4a7c59; padding: 30px 20px; text-align: center; color: white; }
                .header h2 { margin: 0; font-size: 24px; }
                .content { padding: 30px; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #888; background: #f9f9f9; border-top: 1px solid #e0e0e0; }
                .test-badge { background: #ffc107; color: #333; padding: 5px 10px; border-radius: 5px; font-size: 12px; display: inline-block; margin-bottom: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>" . htmlspecialchars($site_title) . "</h2>
                </div>
                <div class='content'>
                    <div class='test-badge'>
                        <i class='fas fa-flask'></i> TEST EMAIL
                    </div>
                    " . nl2br(htmlspecialchars($test_message)) . "
                    <hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'>
                    <p style='font-size: 12px; color: #666;'>
                        <strong>Note:</strong> This is a test email from your admin panel. 
                        If you received this, your email configuration is working correctly.
                    </p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " " . htmlspecialchars($site_title) . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        if (sendEmailWithErrorHandling($test_email, $test_subject, $html_message)) {
            $message = 'Test email sent successfully to ' . htmlspecialchars($test_email);
            if (isset($_SESSION['email_debug'])) {
                $message .= ' (Logged to file)';
                unset($_SESSION['email_debug']);
            }
        } else {
            $error = 'Failed to send test email. Please check your mail server configuration.';
        }
    }
}

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscribed,
                SUM(CASE WHEN status = 'bounced' THEN 1 ELSE 0 END) as bounced,
                SUM(CASE WHEN DATE(subscribed_at) = CURDATE() THEN 1 ELSE 0 END) as today
              FROM subscribers";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get subscribers list with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$where_conditions = [];
$params = [];
$types = "";

if ($status_filter && in_array($status_filter, ['active', 'unsubscribed', 'bounced'])) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search) {
    $where_conditions[] = "(email LIKE ? OR name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total
$count_sql = "SELECT COUNT(*) as total FROM subscribers $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_subscribers = $count_result->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

$total_pages = $total_subscribers > 0 ? ceil($total_subscribers / $per_page) : 1;

// Get subscribers
$sql = "SELECT * FROM subscribers $where_clause ORDER BY subscribed_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$subscribers = $stmt->get_result();
$stmt->close();

// Helper function for date formatting
if (!function_exists('formatDate')) {
    function formatDate($date) {
        return $date ? date('M j, Y g:i A', strtotime($date)) : 'Never';
    }
}

$site_title = getSetting('site_title', 'Painlesslyf');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Subscribers - Admin Panel</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <style>
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
            -webkit-font-smoothing: antialiased;
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
        
        .admin-sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .admin-sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .admin-sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            position: sticky;
            top: 0;
            background: linear-gradient(135deg, #2c3e50 0%, #1e2b37 100%);
            z-index: 5;
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
            color: rgba(255,255,255,0.9);
        }
        
        .sidebar-header p {
            font-size: 14px;
            color: rgba(255,255,255,0.6);
            margin-top: 5px;
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
            font-size: 18px;
        }
        
        .sidebar-menu-item:hover,
        .sidebar-menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #4a7c59;
        }
        
        .sidebar-menu-item.active {
            background: rgba(74, 124, 89, 0.2);
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
            align-items: center;
        }
        
        .form-control {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .btn-primary, .btn-secondary, .btn-danger, .btn-success {
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
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4a7c59;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2c4a3b;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .subscribers-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .subscribers-table table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .subscribers-table th,
        .subscribers-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .subscribers-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .subscribers-table tr:hover {
            background: #f9f9f9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-active {
            background: #28a745;
            color: white;
        }
        
        .status-unsubscribed {
            background: #dc3545;
            color: white;
        }
        
        .status-bounced {
            background: #ffc107;
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
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
        
        .btn-edit {
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 3000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            font-size: 20px;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
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
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .checkbox-column {
            width: 30px;
        }
        
        .bulk-actions {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .export-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .export-section h3 {
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .export-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
            flex-wrap: wrap;
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
        
        .info-badge {
            background: #e7f3ff;
            border-left: 3px solid #4a7c59;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            font-size: 13px;
            color: #0066cc;
        }
        
        @media (max-width: 768px) {
            .export-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .export-form select,
            .export-form button {
                width: 100%;
            }
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
                    <h1>Newsletter Subscribers</h1>
                    <p>Manage your email subscribers and send newsletters</p>
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
            
            <!-- Local Development Notice -->
            <div class="info-badge">
                <i class="fas fa-info-circle"></i> 
                <strong>Local Development Mode:</strong> Emails are being logged to <code>logs/email_log.txt</code> instead of being sent. 
                This will work normally when deployed to Hostinger.
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['total'] ?? 0); ?></div>
                    <div class="stat-label">Total Subscribers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value" style="color: #28a745;"><?php echo number_format($stats['active'] ?? 0); ?></div>
                    <div class="stat-label">Active</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-value" style="color: #dc3545;"><?php echo number_format($stats['unsubscribed'] ?? 0); ?></div>
                    <div class="stat-label">Unsubscribed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    <div class="stat-value"><?php echo number_format($stats['today'] ?? 0); ?></div>
                    <div class="stat-label">New Today</div>
                </div>
            </div>
            
            <!-- Export Section -->
            <div class="export-section">
                <h3><i class="fas fa-download"></i> Export Subscribers</h3>
                <form method="POST" class="export-form">
                    <select name="export_status" class="form-control" style="width: auto;">
                        <option value="">All Subscribers</option>
                        <option value="active">Active Only</option>
                        <option value="unsubscribed">Unsubscribed Only</option>
                        <option value="bounced">Bounced Only</option>
                    </select>
                    <button type="submit" name="export_csv" class="btn-success">
                        <i class="fas fa-file-csv"></i> Export as CSV
                    </button>
                </form>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <form method="GET" action="subscribers.php" style="display: flex; gap: 10px; flex-wrap: wrap; flex: 1;">
                    <select name="status" class="form-control" style="width: auto;">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="unsubscribed" <?php echo $status_filter === 'unsubscribed' ? 'selected' : ''; ?>>Unsubscribed</option>
                        <option value="bounced" <?php echo $status_filter === 'bounced' ? 'selected' : ''; ?>>Bounced</option>
                    </select>
                    
                    <input type="text" name="search" class="form-control" placeholder="Search by email or name..." 
                           value="<?php echo htmlspecialchars($search); ?>" style="min-width: 250px;">
                    
                    <button type="submit" class="btn-primary">Filter</button>
                    <a href="subscribers.php" class="btn-secondary">Reset</a>
                </form>
                
                <button onclick="openTestEmailModal()" class="btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Test Email
                </button>
            </div>
            
            <form method="POST" action="subscribers.php" id="bulkForm">
                <div class="bulk-actions">
                    <button type="submit" name="bulk_delete" class="btn-danger" onclick="return confirm('Are you sure you want to delete selected subscribers?')">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                </div>
                
                <div class="subscribers-table">
                    <table>
                        <thead>
                            <tr>
                                <th class="checkbox-column">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Subscribed Date</th>
                                <th>Unsubscribed Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($subscribers && $subscribers->num_rows > 0): ?>
                                <?php while ($sub = $subscribers->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_subscribers[]" value="<?php echo $sub['id']; ?>" class="subscriber-checkbox">
                                    </td>
                                    <td><?php echo htmlspecialchars($sub['email']); ?></td>
                                    <td><?php echo htmlspecialchars($sub['name'] ?: '-'); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $sub['status']; ?>">
                                            <?php echo ucfirst($sub['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo formatDate($sub['subscribed_at']); ?></td>
                                    <td><?php echo $sub['unsubscribed_at'] ? formatDate($sub['unsubscribed_at']) : '-'; ?></td>
                                    <td class="action-buttons">
                                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('Update subscriber status?');">
                                            <input type="hidden" name="subscriber_id" value="<?php echo $sub['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="form-control" style="padding: 4px 8px; font-size: 12px; width: auto;">
                                                <option value="active" <?php echo $sub['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="unsubscribed" <?php echo $sub['status'] == 'unsubscribed' ? 'selected' : ''; ?>>Unsubscribed</option>
                                                <option value="bounced" <?php echo $sub['status'] == 'bounced' ? 'selected' : ''; ?>>Bounced</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('Delete this subscriber?');">
                                            <input type="hidden" name="subscriber_id" value="<?php echo $sub['id']; ?>">
                                            <button type="submit" name="delete_subscriber" class="btn-icon btn-delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 60px;">
                                        <div class="empty-state">
                                            <i class="fas fa-users"></i>
                                            <p>No subscribers found.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php
                    $query_params = $_GET;
                    $query_params['page'] = $i;
                    $query_string = http_build_query($query_params);
                    ?>
                    <a href="subscribers.php?<?php echo $query_string; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Test Email Modal -->
    <div id="testEmailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-paper-plane"></i> Send Test Email</h3>
                <button class="modal-close" onclick="closeTestEmailModal()">&times;</button>
            </div>
            <div class="info-badge" style="margin-bottom: 15px;">
                <i class="fas fa-info-circle"></i> 
                In local development, emails are logged to <code>logs/email_log.txt</code>. On Hostinger, they'll be sent normally.
            </div>
            <form method="POST" action="subscribers.php">
                <div class="form-group">
                    <label>To Email Address</label>
                    <input type="email" name="test_email" placeholder="Enter email address" required>
                </div>
                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="test_subject" placeholder="Email subject" required>
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="test_message" placeholder="Enter your test message here..." required></textarea>
                </div>
                <button type="submit" name="send_test_email" class="btn-primary" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> Send Test Email
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const adminSidebar = document.getElementById('adminSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            adminSidebar.classList.toggle('sidebar-open');
            sidebarOverlay.classList.toggle('active');
            const icon = mobileMenuToggle.querySelector('i');
            icon.className = adminSidebar.classList.contains('sidebar-open') ? 'fas fa-times' : 'fas fa-bars';
            document.body.style.overflow = adminSidebar.classList.contains('sidebar-open') ? 'hidden' : '';
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
        document.querySelectorAll('.sidebar-menu-item').forEach(link => {
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
        const checkboxes = document.querySelectorAll('.subscriber-checkbox');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                });
            });
        }
        
        // Test Email Modal
        function openTestEmailModal() {
            document.getElementById('testEmailModal').classList.add('active');
        }
        
        function closeTestEmailModal() {
            document.getElementById('testEmailModal').classList.remove('active');
        }
        
        // Close modal on overlay click
        window.onclick = function(event) {
            const modal = document.getElementById('testEmailModal');
            if (event.target == modal) {
                closeTestEmailModal();
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert.parentNode) alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>