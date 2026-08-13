<?php
// Include required files - correct paths
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db-connection.php';
require_once dirname(__DIR__) . '/includes/functions.php';

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

// Initialize variables
$success_message = '';
$error_message = '';

// Get all current settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Helper function to get setting with default
function getSettingValue($key, $default = '') {
    global $settings;
    return isset($settings[$key]) ? $settings[$key] : $default;
}

// Helper function to upload image
function uploadImage($file, $type = 'navbar') {
    $upload_dir = dirname(__DIR__) . '/assets/logo/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)];
    }
    
    // Validate file size (max 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 2MB.'];
    }
    
    // Generate unique filename
    $new_filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
    $target_file = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Return relative path from root
        return ['success' => true, 'path' => 'assets/logo/' . $new_filename];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file.'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_navbar':
                // Build background color with opacity
                $bg_color = $_POST['navbar_background_color'] ?? '#ffffff';
                $bg_opacity = $_POST['navbar_background_opacity'] ?? '0.95';
                
                // Convert hex to rgb
                $r = hexdec(substr($bg_color, 1, 2));
                $g = hexdec(substr($bg_color, 3, 2));
                $b = hexdec(substr($bg_color, 5, 2));
                $rgba = "rgba($r, $g, $b, $bg_opacity)";
                
                $settings_to_update = [
                    // Navbar appearance
                    'navbar_background_type' => $_POST['navbar_background_type'] ?? 'solid',
                    'navbar_background' => $rgba,
                    'navbar_blur' => $_POST['navbar_blur'] ?? '10',
                    'navbar_text_color' => $_POST['navbar_text_color'] ?? '#333333',
                    'navbar_hover_color' => $_POST['navbar_hover_color'] ?? '#4a7c59',
                    
                    // Dropdown styling settings
                    'navbar_dropdown_bg' => $_POST['navbar_dropdown_bg'] ?? '#ffffff',
                    'navbar_dropdown_text' => $_POST['navbar_dropdown_text'] ?? '#333333',
                    'navbar_dropdown_hover_bg' => $_POST['navbar_dropdown_hover_bg'] ?? '#4a7c59',
                    'navbar_dropdown_hover_text' => $_POST['navbar_dropdown_hover_text'] ?? '#ffffff',
                    
                    // Navbar sizing
                    'navbar_padding' => $_POST['navbar_padding'] ?? '20',
                    'navbar_logo_height' => $_POST['navbar_logo_height'] ?? '45',
                    
                    // Menu text
                    'navbar_menu_home' => $_POST['navbar_menu_home'] ?? 'Home',
                    'navbar_menu_blog' => $_POST['navbar_menu_blog'] ?? 'Blog',
                    'navbar_menu_audio' => $_POST['navbar_menu_audio'] ?? 'Audio',
                    'navbar_menu_about' => $_POST['navbar_menu_about'] ?? 'About',
                    'navbar_menu_contact' => $_POST['navbar_menu_contact'] ?? 'Contact',
                    
                    // Menu links
                    'navbar_link_home' => $_POST['navbar_link_home'] ?? 'index.php',
                    'navbar_link_blog' => $_POST['navbar_link_blog'] ?? 'blog.php',
                    'navbar_link_audio' => $_POST['navbar_link_audio'] ?? 'audio.php',
                    'navbar_link_about' => $_POST['navbar_link_about'] ?? 'about.php',
                    'navbar_link_contact' => $_POST['navbar_link_contact'] ?? 'contact.php',
                    
                    // Menu icons
                    'navbar_icon_home' => $_POST['navbar_icon_home'] ?? 'fas fa-home',
                    'navbar_icon_blog' => $_POST['navbar_icon_blog'] ?? 'fas fa-pencil-alt',
                    'navbar_icon_audio' => $_POST['navbar_icon_audio'] ?? 'fas fa-headphones',
                    'navbar_icon_about' => $_POST['navbar_icon_about'] ?? 'fas fa-user',
                    'navbar_icon_contact' => $_POST['navbar_icon_contact'] ?? 'fas fa-envelope',
                    
                    // Mobile & behavior
                    'navbar_mobile_breakpoint' => $_POST['navbar_mobile_breakpoint'] ?? '992',
                    'navbar_show_theme_toggle' => isset($_POST['navbar_show_theme_toggle']) ? '1' : '0',
                    'navbar_hide_on_scroll' => isset($_POST['navbar_hide_on_scroll']) ? '1' : '0',
                    'navbar_scroll_threshold' => $_POST['navbar_scroll_threshold'] ?? '100'
                ];
                
                // Handle logo upload
                if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['site_logo'], 'navbar');
                    if ($upload_result['success']) {
                        $settings_to_update['site_logo'] = $upload_result['path'];
                        $success_message = 'Logo uploaded successfully! ';
                    } else {
                        $error_message = $upload_result['error'];
                    }
                }
                
                $updated = 0;
                foreach ($settings_to_update as $key => $value) {
                    if (updateSetting($key, $value)) {
                        $updated++;
                    }
                }
                
                if ($updated > 0 && empty($error_message)) {
                    $success_message = ($success_message ?: '') . 'Navbar settings updated successfully!';
                    // Refresh settings
                    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
                    $settings = [];
                    while ($row = $result->fetch_assoc()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
                break;
                
            case 'reset_navbar':
                $default_settings = [
                    'navbar_background_type' => 'solid',
                    'navbar_background' => 'rgba(255,255,255,0.95)',
                    'navbar_blur' => '10',
                    'navbar_text_color' => '#333333',
                    'navbar_hover_color' => '#4a7c59',
                    'navbar_dropdown_bg' => '#ffffff',
                    'navbar_dropdown_text' => '#333333',
                    'navbar_dropdown_hover_bg' => '#4a7c59',
                    'navbar_dropdown_hover_text' => '#ffffff',
                    'navbar_padding' => '20',
                    'navbar_logo_height' => '45',
                    'navbar_menu_home' => 'Home',
                    'navbar_menu_blog' => 'Blog',
                    'navbar_menu_audio' => 'Audio',
                    'navbar_menu_about' => 'About',
                    'navbar_menu_contact' => 'Contact',
                    'navbar_link_home' => 'index.php',
                    'navbar_link_blog' => 'blog.php',
                    'navbar_link_audio' => 'audio.php',
                    'navbar_link_about' => 'about.php',
                    'navbar_link_contact' => 'contact.php',
                    'navbar_icon_home' => 'fas fa-home',
                    'navbar_icon_blog' => 'fas fa-pencil-alt',
                    'navbar_icon_audio' => 'fas fa-headphones',
                    'navbar_icon_about' => 'fas fa-user',
                    'navbar_icon_contact' => 'fas fa-envelope',
                    'navbar_mobile_breakpoint' => '992',
                    'navbar_show_theme_toggle' => '1',
                    'navbar_hide_on_scroll' => '1',
                    'navbar_scroll_threshold' => '100'
                ];
                
                $updated = 0;
                foreach ($default_settings as $key => $value) {
                    if (updateSetting($key, $value)) {
                        $updated++;
                    }
                }
                
                if ($updated > 0) {
                    $success_message = 'Navbar settings reset to default!';
                    // Refresh settings
                    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
                    $settings = [];
                    while ($row = $result->fetch_assoc()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
                break;
        }
    }
}

// Get current navbar background for preview
$current_bg = getSettingValue('navbar_background', 'rgba(255,255,255,0.95)');
if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([0-9.]+))?\)/', $current_bg, $matches)) {
    $current_bg_hex = sprintf('#%02x%02x%02x', $matches[1], $matches[2], $matches[3]);
    $current_bg_opacity = isset($matches[4]) ? $matches[4] : '0.95';
} else {
    $current_bg_hex = '#ffffff';
    $current_bg_opacity = '0.95';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navbar Style Settings - Admin Panel</title>
    
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
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f9;
            color: #333;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .admin-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2c3e50 0%, #1e2b37 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
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
            padding: 20px 0;
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
        
        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: #f4f6f9;
            min-height: 100vh;
        }
        
        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 15px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .user-info {
            text-align: right;
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
        
        /* Settings Container */
        .settings-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Messages */
        .alert {
            padding: 15px 20px;
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
        
        .alert i {
            font-size: 20px;
        }
        
        /* Settings Panel */
        .settings-panel {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .panel-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .panel-header h2 {
            font-size: 22px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .panel-header p {
            color: #666;
            font-size: 14px;
        }
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group label i {
            margin-right: 5px;
            color: #4a7c59;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #4a7c59;
            box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        /* Color Picker */
        .color-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .color-input-group input[type="color"] {
            width: 60px;
            height: 42px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            padding: 2px;
        }
        
        .color-input-group .form-control {
            flex: 1;
        }
        
        /* Range Slider */
        .range-group {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
        }
        
        .range-group label {
            margin-bottom: 0;
            min-width: 50px;
        }
        
        .range-group input[type="range"] {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: #e0e0e0;
        }
        
        .range-group span {
            min-width: 40px;
            text-align: right;
            color: #666;
        }
        
        /* File Upload */
        .file-upload {
            position: relative;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .file-upload-label {
            padding: 12px 25px;
            background: #f8f9fa;
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .file-upload-label:hover {
            border-color: #4a7c59;
            background: #f1f8f1;
        }
        
        .file-upload-label i {
            color: #4a7c59;
            font-size: 20px;
        }
        
        .file-info {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .preview-image {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            background: white;
            padding: 5px;
        }
        
        /* Icon Selector */
        .icon-selector {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .icon-preview {
            width: 42px;
            height: 42px;
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #4a7c59;
        }
        
        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        /* Navbar Live Preview */
        .navbar-preview {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #e0e0e0;
        }
        
        .preview-title {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .live-navbar {
            background: <?php echo getSettingValue('navbar_background', 'rgba(255,255,255,0.95)'); ?>;
            padding: 15px 25px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
            <?php if(getSettingValue('navbar_background_type', 'solid') == 'frosted'): ?>
            backdrop-filter: blur(<?php echo getSettingValue('navbar_blur', '10'); ?>px);
            <?php endif; ?>
        }
        
        .live-logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .live-logo-icon {
            width: 40px;
            height: 40px;
            background: <?php echo getSettingValue('primary_color', '#4a7c59'); ?>;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .live-logo-text {
            font-weight: 700;
            font-size: 18px;
            color: <?php echo getSettingValue('navbar_text_color', '#333333'); ?>;
        }
        
        .live-menu {
            display: flex;
            gap: 20px;
        }
        
        .live-menu-item {
            font-size: 14px;
            color: <?php echo getSettingValue('navbar_text_color', '#333333'); ?>;
            padding: 5px 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .live-menu-item:hover {
            color: <?php echo getSettingValue('navbar_hover_color', '#4a7c59'); ?>;
        }
        
        .live-hamburger {
            display: none;
            width: 30px;
            height: 20px;
            flex-direction: column;
            justify-content: space-between;
            cursor: pointer;
        }
        
        .live-hamburger span {
            width: 100%;
            height: 3px;
            background: <?php echo getSettingValue('navbar_text_color', '#333333'); ?>;
            border-radius: 3px;
        }
        
        /* Action Buttons */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #4a7c59;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2c4a3b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 124, 89, 0.3);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #4a7c59;
            margin: 25px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #eee;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .admin-sidebar {
                width: 80px;
            }
            
            .sidebar-header h3,
            .sidebar-header p,
            .sidebar-menu-item span,
            .sidebar-menu-label {
                display: none;
            }
            
            .sidebar-header img {
                max-width: 50px;
                padding: 5px;
            }
            
            .admin-main {
                margin-left: 80px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
        }
        
        @media (max-width: 768px) {
            .admin-main {
                margin-left: 0;
                padding: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .top-nav {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .top-nav-user {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php $current_page = basename(__FILE__); include __DIR__ . '/includes/sidebar.php'; ?>
        <!-- Main Content -->
        <div class="admin-main">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="top-nav-title">
                    <h1>Navbar Style Settings</h1>
                    <p>Customize the appearance, colors, text, and behavior of your website navigation bar</p>
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
            
            <div class="settings-container">
                <!-- Messages -->
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>
                
                <!-- Navbar Settings Form -->
                <div class="settings-panel">
                    <div class="panel-header">
                        <h2>Navigation Bar Customization</h2>
                        <p>Control colors, background, text, dropdown styling, and all menu items</p>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data" id="navbarForm">
                        <input type="hidden" name="action" value="update_navbar">
                        
                        <!-- Live Preview -->
                        <div class="navbar-preview">
                            <div class="preview-title">
                                <i class="fas fa-eye"></i> Live Preview
                            </div>
                            <div class="live-navbar" id="liveNavbarPreview">
                                <div class="live-logo">
                                    <div class="live-logo-icon">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <div class="live-logo-text" id="liveLogoText">LOGO</div>
                                </div>
                                <div class="live-menu" id="liveMenu">
                                    <div class="live-menu-item">Home</div>
                                    <div class="live-menu-item">Blog</div>
                                    <div class="live-menu-item">Audio</div>
                                    <div class="live-menu-item">About</div>
                                    <div class="live-menu-item">Contact</div>
                                </div>
                                <div class="live-hamburger" id="liveHamburger">
                                    <span></span>
                                    <span></span>
                                    <span></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Navbar Appearance Section -->
                        <h3 class="section-title"><i class="fas fa-palette"></i> Navbar Appearance</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-layer-group"></i> Background Type</label>
                                <select name="navbar_background_type" id="navbar_bg_type" class="form-control" onchange="updatePreview()">
                                    <option value="solid" <?php echo getSettingValue('navbar_background_type', 'solid') == 'solid' ? 'selected' : ''; ?>>Solid Color</option>
                                    <option value="frosted" <?php echo getSettingValue('navbar_background_type') == 'frosted' ? 'selected' : ''; ?>>Frosted Glass (Blur)</option>
                                    <option value="transparent" <?php echo getSettingValue('navbar_background_type') == 'transparent' ? 'selected' : ''; ?>>Transparent</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="bg_color_group">
                                <label><i class="fas fa-fill-drip"></i> Background Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="navbar_background_color" id="navbar_bg_color" value="<?php echo $current_bg_hex; ?>" onchange="updateBackgroundColor(this.value)">
                                    <input type="text" class="form-control" id="navbar_bg_hex" value="<?php echo $current_bg_hex; ?>" readonly>
                                </div>
                                <div class="range-group">
                                    <label>Opacity:</label>
                                    <input type="range" name="navbar_background_opacity" id="navbar_bg_opacity" min="0" max="1" step="0.05" value="<?php echo $current_bg_opacity; ?>" oninput="updateBackgroundOpacity(this.value)">
                                    <span id="opacity_value"><?php echo $current_bg_opacity; ?></span>
                                </div>
                            </div>
                            
                            <div class="form-group" id="blur_group" style="display: <?php echo getSettingValue('navbar_background_type', 'solid') == 'frosted' ? 'block' : 'none'; ?>;">
                                <label><i class="fas fa-tint"></i> Blur Amount (px)</label>
                                <input type="range" name="navbar_blur" id="navbar_blur" min="0" max="20" step="1" value="<?php echo getSettingValue('navbar_blur', '10'); ?>" oninput="updateBlurValue(this.value)">
                                <span id="blur_value"><?php echo getSettingValue('navbar_blur', '10'); ?>px</span>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-font"></i> Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="navbar_text_color" id="navbar_text_color" value="<?php echo getSettingValue('navbar_text_color', '#333333'); ?>" onchange="updatePreview()">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('navbar_text_color', '#333333'); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-link"></i> Hover/Active Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="navbar_hover_color" id="navbar_hover_color" value="<?php echo getSettingValue('navbar_hover_color', '#4a7c59'); ?>" onchange="updatePreview()">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('navbar_hover_color', '#4a7c59'); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-arrows-alt"></i> Navbar Padding (px)</label>
                                <input type="number" name="navbar_padding" id="navbar_padding" class="form-control" min="10" max="40" value="<?php echo getSettingValue('navbar_padding', '20'); ?>" onchange="updatePreview()">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-image"></i> Logo Height (px)</label>
                                <input type="number" name="navbar_logo_height" id="navbar_logo_height" class="form-control" min="30" max="80" value="<?php echo getSettingValue('navbar_logo_height', '45'); ?>" onchange="updatePreview()">
                            </div>
                        </div>
                        
                        <!-- Dropdown Menu Styling -->
                        <h3 class="section-title"><i class="fas fa-bars"></i> Dropdown Menu Styling</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-fill"></i> Dropdown Background</label>
                                <div class="color-input-group">
                                    <input type="color" name="navbar_dropdown_bg" id="dropdown_bg" value="<?php echo getSettingValue('navbar_dropdown_bg', '#ffffff'); ?>" onchange="updatePreview()">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('navbar_dropdown_bg', '#ffffff'); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-font"></i> Dropdown Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="navbar_dropdown_text" id="dropdown_text" value="<?php echo getSettingValue('navbar_dropdown_text', '#333333'); ?>" onchange="updatePreview()">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('navbar_dropdown_text', '#333333'); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-fill-drip"></i> Dropdown Hover Background</label>
                                <div class="color-input-group">
                                    <input type="color" name="navbar_dropdown_hover_bg" id="dropdown_hover_bg" value="<?php echo getSettingValue('navbar_dropdown_hover_bg', '#4a7c59'); ?>" onchange="updatePreview()">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('navbar_dropdown_hover_bg', '#4a7c59'); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-font"></i> Dropdown Hover Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="navbar_dropdown_hover_text" id="dropdown_hover_text" value="<?php echo getSettingValue('navbar_dropdown_hover_text', '#ffffff'); ?>" onchange="updatePreview()">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('navbar_dropdown_hover_text', '#ffffff'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Logo Section -->
                        <h3 class="section-title"><i class="fas fa-image"></i> Logo</h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Current Logo</label>
                                <div class="file-upload">
                                    <img src="../<?php echo getSettingValue('site_logo', 'assets/logo/refreshing-dews-logo.png'); ?>" class="preview-image" alt="Current Logo">
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Upload New Logo</label>
                                <div class="file-upload">
                                    <input type="file" name="site_logo" id="site_logo" accept="image/*">
                                    <label for="site_logo" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Choose Logo Image</span>
                                    </label>
                                    <div class="file-info">Recommended size: 150x50px. Allowed: JPG, PNG, GIF, SVG, WebP</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Menu Items Section -->
                        <h3 class="section-title"><i class="fas fa-list"></i> Menu Items</h3>
                        <div class="form-grid">
                            <?php
                            $menu_items = [
                                'home' => ['Home', 'index.php', 'fas fa-home'],
                                'blog' => ['Blog', 'blog.php', 'fas fa-pencil-alt'],
                                'audio' => ['Audio', 'audio.php', 'fas fa-headphones'],
                                'about' => ['About', 'about.php', 'fas fa-user'],
                                'contact' => ['Contact', 'contact.php', 'fas fa-envelope']
                            ];
                            
                            foreach ($menu_items as $key => $item):
                                $text = getSettingValue("navbar_menu_$key", $item[0]);
                                $link = getSettingValue("navbar_link_$key", $item[1]);
                                $icon = getSettingValue("navbar_icon_$key", $item[2]);
                            ?>
                            <div class="form-group">
                                <label><i class="<?php echo $item[2]; ?>"></i> <?php echo ucfirst($key); ?> Text</label>
                                <input type="text" name="navbar_menu_<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($text); ?>" onchange="updatePreview()">
                            </div>
                            <div class="form-group">
                                <label><?php echo ucfirst($key); ?> Link</label>
                                <input type="text" name="navbar_link_<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($link); ?>">
                            </div>
                            <div class="form-group">
                                <label><?php echo ucfirst($key); ?> Icon</label>
                                <div class="icon-selector">
                                    <div class="icon-preview" id="icon_preview_<?php echo $key; ?>">
                                        <i class="<?php echo $icon; ?>"></i>
                                    </div>
                                    <input type="text" name="navbar_icon_<?php echo $key; ?>" id="icon_<?php echo $key; ?>" class="form-control" value="<?php echo htmlspecialchars($icon); ?>" placeholder="fas fa-icon-name" onchange="updateIconPreview('<?php echo $key; ?>')">
                                </div>
                                <div class="file-info">Font Awesome icon class (e.g., fas fa-home)</div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Mobile & Behavior -->
                        <h3 class="section-title"><i class="fas fa-mobile-alt"></i> Mobile & Behavior</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-arrows-alt"></i> Mobile Breakpoint (px)</label>
                                <input type="number" name="navbar_mobile_breakpoint" id="mobile_breakpoint" class="form-control" min="768" max="1200" value="<?php echo getSettingValue('navbar_mobile_breakpoint', '992'); ?>" onchange="updatePreview()">
                                <div class="file-info">Screen width below this value shows hamburger menu</div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="navbar_show_theme_toggle" id="navbar_show_theme_toggle" <?php echo getSettingValue('navbar_show_theme_toggle', '1') == '1' ? 'checked' : ''; ?>>
                                    <label for="navbar_show_theme_toggle">Show Dark/Light Theme Toggle</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="navbar_hide_on_scroll" id="navbar_hide_on_scroll" <?php echo getSettingValue('navbar_hide_on_scroll', '1') == '1' ? 'checked' : ''; ?>>
                                    <label for="navbar_hide_on_scroll">Hide Navbar on Scroll Down</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-arrow-down"></i> Scroll Threshold (px)</label>
                                <input type="number" name="navbar_scroll_threshold" class="form-control" min="50" max="300" value="<?php echo getSettingValue('navbar_scroll_threshold', '100'); ?>">
                                <div class="file-info">Pixels to scroll before hiding navbar</div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Navbar Settings
                            </button>
                            <button type="button" class="btn btn-danger" onclick="resetSettings()">
                                <i class="fas fa-undo"></i> Reset to Default
                            </button>
                        </div>
                    </form>
                    
                    <form method="POST" id="resetForm" style="display: none;">
                        <input type="hidden" name="action" value="reset_navbar">
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Live preview update
        function updatePreview() {
            const bgType = document.getElementById('navbar_bg_type').value;
            const textColor = document.getElementById('navbar_text_color').value;
            const hoverColor = document.getElementById('navbar_hover_color').value;
            const padding = document.getElementById('navbar_padding')?.value || '20';
            const logoHeight = document.getElementById('navbar_logo_height')?.value || '45';
            const mobileBreakpoint = document.getElementById('mobile_breakpoint')?.value || '992';
            const bgColor = document.getElementById('navbar_bg_color')?.value || '#ffffff';
            const bgOpacity = document.getElementById('navbar_bg_opacity')?.value || '0.95';
            const blur = document.getElementById('navbar_blur')?.value || '10';
            
            const preview = document.getElementById('liveNavbarPreview');
            const menuItems = document.querySelectorAll('.live-menu-item');
            const hamburger = document.getElementById('liveHamburger');
            const menu = document.getElementById('liveMenu');
            const logoText = document.getElementById('liveLogoText');
            
            // Update text colors
            menuItems.forEach(item => {
                item.style.color = textColor;
                item.onmouseenter = () => item.style.color = hoverColor;
                item.onmouseleave = () => item.style.color = textColor;
            });
            
            // Update logo text
            const homeText = document.querySelector('[name="navbar_menu_home"]')?.value || 'Home';
            logoText.textContent = homeText;
            
            // Update background
            const r = parseInt(bgColor.substr(1,2), 16);
            const g = parseInt(bgColor.substr(3,2), 16);
            const b = parseInt(bgColor.substr(5,2), 16);
            const rgba = `rgba(${r}, ${g}, ${b}, ${bgOpacity})`;
            
            if (bgType === 'transparent') {
                preview.style.background = 'transparent';
                preview.style.backdropFilter = 'none';
            } else if (bgType === 'frosted') {
                preview.style.background = rgba;
                preview.style.backdropFilter = `blur(${blur}px)`;
            } else {
                preview.style.background = rgba;
                preview.style.backdropFilter = 'none';
            }
            
            // Update padding
            preview.style.padding = `${padding}px 25px`;
            
            // Update logo height
            const logoIcon = document.querySelector('.live-logo-icon');
            if (logoIcon) {
                logoIcon.style.width = `${logoHeight}px`;
                logoIcon.style.height = `${logoHeight}px`;
            }
            
            // Update mobile breakpoint
            let styleElement = document.getElementById('dynamic-breakpoint-style');
            if (styleElement) styleElement.remove();
            
            const newStyle = document.createElement('style');
            newStyle.id = 'dynamic-breakpoint-style';
            newStyle.textContent = `
                @media (max-width: ${mobileBreakpoint}px) {
                    .live-menu {
                        display: none !important;
                    }
                    .live-hamburger {
                        display: flex !important;
                    }
                }
                @media (min-width: ${parseInt(mobileBreakpoint) + 1}px) {
                    .live-menu {
                        display: flex !important;
                    }
                    .live-hamburger {
                        display: none !important;
                    }
                }
            `;
            document.head.appendChild(newStyle);
            
            // Update hamburger color
            const hamburgerSpans = document.querySelectorAll('.live-hamburger span');
            hamburgerSpans.forEach(span => {
                span.style.background = textColor;
            });
        }
        
        function updateBackgroundColor(color) {
            document.getElementById('navbar_bg_hex').value = color;
            updatePreview();
        }
        
        function updateBackgroundOpacity(opacity) {
            document.getElementById('opacity_value').textContent = opacity;
            updatePreview();
        }
        
        function updateBlurValue(value) {
            document.getElementById('blur_value').textContent = value + 'px';
            updatePreview();
        }
        
        function updateIconPreview(key) {
            const iconInput = document.getElementById(`icon_${key}`);
            const iconPreview = document.getElementById(`icon_preview_${key}`);
            if (iconPreview && iconInput) {
                iconPreview.innerHTML = `<i class="${iconInput.value}"></i>`;
            }
        }
        
        function resetSettings() {
            if (confirm('Are you sure you want to reset all navbar settings to default? This cannot be undone.')) {
                document.getElementById('resetForm').submit();
            }
        }
        
        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
        
        // Toggle background options based on type
        function toggleBackgroundOptions() {
            const type = document.getElementById('navbar_bg_type').value;
            const colorGroup = document.getElementById('bg_color_group');
            const blurGroup = document.getElementById('blur_group');
            
            if (type === 'transparent') {
                colorGroup.style.display = 'none';
                blurGroup.style.display = 'none';
            } else if (type === 'frosted') {
                colorGroup.style.display = 'block';
                blurGroup.style.display = 'block';
            } else {
                colorGroup.style.display = 'block';
                blurGroup.style.display = 'none';
            }
            updatePreview();
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('navbar_bg_type').addEventListener('change', toggleBackgroundOptions);
            updatePreview();
            toggleBackgroundOptions();
            
            // Image preview for logo upload
            const logoInput = document.getElementById('site_logo');
            if (logoInput) {
                logoInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewImg = document.querySelector('.file-upload .preview-image');
                            if (previewImg) {
                                previewImg.src = e.target.result;
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Add input listeners for live preview
            const inputs = ['navbar_menu_home', 'navbar_menu_blog', 'navbar_menu_audio', 'navbar_menu_about', 'navbar_menu_contact'];
            inputs.forEach(input => {
                const element = document.querySelector(`[name="${input}"]`);
                if (element) {
                    element.addEventListener('input', updatePreview);
                }
            });
        });
    </script>
</body>
</html>