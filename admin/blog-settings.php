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
function uploadImage($file, $type = 'blog') {
    $upload_dir = '../assets/uploads/blog/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)];
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB.'];
    }
    
    // Generate unique filename
    $safe_type = preg_replace('/[^a-z0-9_-]/i', '', $type) ?: 'blog';
    $new_filename = $safe_type . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $target_file = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Return relative path from root
        $relative_path = 'assets/uploads/blog/' . $new_filename;
        return ['success' => true, 'path' => $relative_path];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file.'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_blog_settings':
                $settings_to_update = [
                    // Blog header settings
                    'blog_header_title' => $_POST['blog_header_title'] ?? 'The Blog',
                    'blog_header_subtitle' => $_POST['blog_header_subtitle'] ?? 'Thoughts, stories, and experiences from the everyday. Honest and unfiltered.',
                    'blog_header_background_type' => $_POST['blog_header_background_type'] ?? 'gradient',
                    'blog_header_background_color' => $_POST['blog_header_background_color'] ?? '#4a7c59',
                    'blog_header_background_gradient_start' => $_POST['blog_header_background_gradient_start'] ?? '#4a7c59',
                    'blog_header_background_gradient_end' => $_POST['blog_header_background_gradient_end'] ?? '#2c4a3b',
                    'blog_header_text_color' => $_POST['blog_header_text_color'] ?? '#ffffff',
                    
                    // Blog grid settings
                    'blog_grid_title' => $_POST['blog_grid_title'] ?? 'Latest Articles',
                    'blog_grid_subtitle' => $_POST['blog_grid_subtitle'] ?? 'Discover stories, thinking, and expertise from writers on any topic.',
                    'blog_per_page' => $_POST['blog_per_page'] ?? '9',
                    'blog_sidebar_enabled' => isset($_POST['blog_sidebar_enabled']) ? '1' : '0',
                    'blog_featured_enabled' => isset($_POST['blog_featured_enabled']) ? '1' : '0',
                    'blog_author_profile_image' => $_POST['blog_author_profile_image'] ?? '',
                    'blog_author_thumbnail_image' => $_POST['blog_author_thumbnail_image'] ?? '',
                    'blog_author_name' => trim($_POST['blog_author_name'] ?? 'COMANDA1'),
                    
                    // Newsletter settings
                    'blog_newsletter_enabled' => isset($_POST['blog_newsletter_enabled']) ? '1' : '0',
                    'blog_newsletter_title' => $_POST['blog_newsletter_title'] ?? 'Never Miss a Post',
                    'blog_newsletter_subtitle' => $_POST['blog_newsletter_subtitle'] ?? 'Subscribe to get the latest articles and audio messages delivered to your inbox.',
                    'blog_newsletter_background_type' => $_POST['blog_newsletter_background_type'] ?? 'gradient',
                    'blog_newsletter_background_color' => $_POST['blog_newsletter_background_color'] ?? '#4a7c59',
                    'blog_newsletter_gradient_start' => $_POST['blog_newsletter_gradient_start'] ?? '#2563eb',
                    'blog_newsletter_gradient_end' => $_POST['blog_newsletter_gradient_end'] ?? '#4a7c59',
                    'blog_newsletter_text_color' => $_POST['blog_newsletter_text_color'] ?? '#ffffff',
                    'blog_newsletter_button_bg' => $_POST['blog_newsletter_button_bg'] ?? '#ffffff',
                    'blog_newsletter_button_text' => $_POST['blog_newsletter_button_text'] ?? '#4a7c59',
                    'blog_newsletter_button_hover_bg' => $_POST['blog_newsletter_button_hover_bg'] ?? '#f0f0f0',
                    
                    // Color settings
                    'blog_background_color' => $_POST['blog_background_color'] ?? '#f9fbf9',
                    'blog_card_background' => $_POST['blog_card_background'] ?? '#ffffff',
                    'blog_title_color' => $_POST['blog_title_color'] ?? '#1a2a1f',
                    'blog_text_color' => $_POST['blog_text_color'] ?? '#6c757d',
                    'blog_meta_color' => $_POST['blog_meta_color'] ?? '#6c757d',
                    'blog_category_background' => $_POST['blog_category_background'] ?? '#4a7c59',
                    'blog_category_color' => $_POST['blog_category_color'] ?? '#ffffff',
                    'blog_button_color' => $_POST['blog_button_color'] ?? '#4a7c59',
                    'blog_button_hover_color' => $_POST['blog_button_hover_color'] ?? '#2c4a3b'
                ];
                
                // Handle header background image upload
                if (isset($_FILES['blog_header_background_image']) && $_FILES['blog_header_background_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['blog_header_background_image'], 'blog');
                    if ($upload_result['success']) {
                        $settings_to_update['blog_header_background_image'] = $upload_result['path'];
                        $success_message = 'Header background image uploaded successfully! ';
                    } else {
                        $error_message = $upload_result['error'];
                    }
                }
                
                // Handle header background image removal
                if (isset($_POST['remove_header_image']) && $_POST['remove_header_image'] == '1') {
                    $settings_to_update['blog_header_background_image'] = '';
                }
                
                // Handle newsletter background image upload
                if (isset($_FILES['blog_newsletter_background_image']) && $_FILES['blog_newsletter_background_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['blog_newsletter_background_image'], 'blog');
                    if ($upload_result['success']) {
                        $settings_to_update['blog_newsletter_background_image'] = $upload_result['path'];
                        $success_message = ($success_message ?: '') . 'Newsletter background image uploaded successfully!';
                    } else {
                        $error_message = $upload_result['error'];
                    }
                }
                
                // Handle newsletter background image removal
                if (isset($_POST['remove_newsletter_image']) && $_POST['remove_newsletter_image'] == '1') {
                    $settings_to_update['blog_newsletter_background_image'] = '';
                }

                // Handle author thumbnail upload
                if (isset($_FILES['blog_author_thumbnail_upload']) && $_FILES['blog_author_thumbnail_upload']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['blog_author_thumbnail_upload'], 'blog_author_thumb');
                    if ($upload_result['success']) {
                        $settings_to_update['blog_author_thumbnail_image'] = $upload_result['path'];
                        $success_message = ($success_message ?: '') . ' Author thumbnail uploaded successfully!';
                    } else {
                        $error_message = $upload_result['error'];
                    }
                }

                if (isset($_POST['remove_author_thumbnail']) && $_POST['remove_author_thumbnail'] == '1') {
                    $settings_to_update['blog_author_thumbnail_image'] = '';
                }

                // Handle writer profile image upload
                if (isset($_FILES['blog_author_profile_image_upload']) && $_FILES['blog_author_profile_image_upload']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['blog_author_profile_image_upload'], 'blog_writer');
                    if ($upload_result['success']) {
                        $settings_to_update['blog_author_profile_image'] = $upload_result['path'];
                        $success_message = ($success_message ?: '') . ' Writer profile image uploaded successfully!';
                    } else {
                        $error_message = $upload_result['error'];
                    }
                }

                // Handle writer profile image removal
                if (isset($_POST['remove_author_image']) && $_POST['remove_author_image'] == '1') {
                    $settings_to_update['blog_author_profile_image'] = '';
                }
                
                $updated = 0;
                foreach ($settings_to_update as $key => $value) {
                    if (updateSetting($key, $value)) {
                        $updated++;
                    }
                }
                
                if ($updated > 0 && empty($error_message)) {
                    $success_message = ($success_message ?: '') . 'Blog settings updated successfully!';
                    // Refresh settings
                    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
                    $settings = [];
                    while ($row = $result->fetch_assoc()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
                break;
                
            case 'reset_blog_settings':
                $default_settings = [
                    // Blog header settings
                    'blog_header_title' => 'The Blog',
                    'blog_header_subtitle' => 'Thoughts, stories, and experiences from the everyday. Honest and unfiltered.',
                    'blog_header_background_type' => 'gradient',
                    'blog_header_background_color' => '#4a7c59',
                    'blog_header_background_gradient_start' => '#4a7c59',
                    'blog_header_background_gradient_end' => '#2c4a3b',
                    'blog_header_background_image' => '',
                    'blog_header_text_color' => '#ffffff',
                    
                    // Blog grid settings
                    'blog_grid_title' => 'Latest Articles',
                    'blog_grid_subtitle' => 'Discover stories, thinking, and expertise from writers on any topic.',
                    'blog_per_page' => '9',
                    'blog_sidebar_enabled' => '1',
                    'blog_featured_enabled' => '1',
                    'blog_author_profile_image' => '',
                    'blog_author_thumbnail_image' => '',
                    'blog_author_name' => 'COMANDA1',
                    
                    // Newsletter settings
                    'blog_newsletter_enabled' => '1',
                    'blog_newsletter_title' => 'Never Miss a Post',
                    'blog_newsletter_subtitle' => 'Subscribe to get the latest articles and audio messages delivered to your inbox.',
                    'blog_newsletter_background_type' => 'gradient',
                    'blog_newsletter_background_color' => '#4a7c59',
                    'blog_newsletter_gradient_start' => '#2563eb',
                    'blog_newsletter_gradient_end' => '#4a7c59',
                    'blog_newsletter_background_image' => '',
                    'blog_newsletter_text_color' => '#ffffff',
                    'blog_newsletter_button_bg' => '#ffffff',
                    'blog_newsletter_button_text' => '#4a7c59',
                    'blog_newsletter_button_hover_bg' => '#f0f0f0',
                    
                    // Color settings
                    'blog_background_color' => '#f9fbf9',
                    'blog_card_background' => '#ffffff',
                    'blog_title_color' => '#1a2a1f',
                    'blog_text_color' => '#6c757d',
                    'blog_meta_color' => '#6c757d',
                    'blog_category_background' => '#4a7c59',
                    'blog_category_color' => '#ffffff',
                    'blog_button_color' => '#4a7c59',
                    'blog_button_hover_color' => '#2c4a3b'
                ];
                
                $updated = 0;
                foreach ($default_settings as $key => $value) {
                    if (updateSetting($key, $value)) {
                        $updated++;
                    }
                }
                
                if ($updated > 0) {
                    $success_message = 'Blog settings reset to default!';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Settings - Admin Panel</title>
    
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
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }

        .author-thumbnail-preview {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: 14px;
            border: 3px solid #ffffff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(74, 124, 89, 0.15);
            display: block;
            margin-top: 12px;
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
        
        /* Preview Sections */
        .preview-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .preview-title {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Blog Header Preview */
        .blog-header-preview {
            padding: 60px 20px;
            text-align: center;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .blog-header-preview h1 {
            font-size: 36px;
            margin-bottom: 15px;
        }
        
        .blog-header-preview p {
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        /* Newsletter Preview */
        .newsletter-preview {
            padding: 50px 20px;
            text-align: center;
            border-radius: 10px;
        }
        
        .newsletter-preview h2 {
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .newsletter-preview p {
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .preview-form {
            display: flex;
            gap: 10px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .preview-form input {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 30px;
        }
        
        .preview-form button {
            padding: 12px 25px;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
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
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
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
                    <h1>Blog Settings</h1>
                    <p>Customize your blog page appearance and functionality</p>
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
                
                <!-- Blog Settings Form -->
                <div class="settings-panel">
                    <div class="panel-header">
                        <h2>Blog Page Settings</h2>
                        <p>Configure headers for the blog listing and individual post pages, grid, newsletter, and colors</p>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_blog_settings">
                        
                        <!-- Blog Header Section -->
                        <h3 class="section-title"><i class="fas fa-header"></i> Blog &amp; Post Header</h3>
                        <p style="margin: -8px 0 20px; color: #666; font-size: 14px;">Applies to the main blog page header and the hero on every individual blog post page.</p>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-heading"></i> Header Title</label>
                                <input type="text" name="blog_header_title" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_header_title', 'The Blog')); ?>" oninput="updateHeaderPreview()">
                            </div>
                            
                            <div class="form-group full-width">
                                <label><i class="fas fa-align-left"></i> Header Subtitle</label>
                                <input type="text" name="blog_header_subtitle" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_header_subtitle', 'Thoughts, stories, and experiences from the everyday. Honest and unfiltered.')); ?>" oninput="updateHeaderPreview()">
                            </div>
                            
                            <div class="form-group">
                                <label>Background Type</label>
                                <select name="blog_header_background_type" id="header_bg_type" class="form-control" onchange="toggleHeaderBackground()">
                                    <option value="gradient" <?php echo getSettingValue('blog_header_background_type', 'gradient') == 'gradient' ? 'selected' : ''; ?>>Gradient</option>
                                    <option value="solid" <?php echo getSettingValue('blog_header_background_type') == 'solid' ? 'selected' : ''; ?>>Solid Color</option>
                                    <option value="image" <?php echo getSettingValue('blog_header_background_type') == 'image' ? 'selected' : ''; ?>>Background Image</option>
                                </select>
                            </div>
                            
                            <div id="header_gradient_options" style="display: <?php echo getSettingValue('blog_header_background_type', 'gradient') == 'gradient' ? 'block' : 'none'; ?>;">
                                <div class="form-group">
                                    <label>Gradient Start Color</label>
                                    <div class="color-input-group">
                                        <input type="color" name="blog_header_background_gradient_start" id="header_gradient_start" value="<?php echo htmlspecialchars(getSettingValue('blog_header_background_gradient_start', '#4a7c59')); ?>" onchange="updateHeaderPreview()">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_header_background_gradient_start', '#4a7c59')); ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Gradient End Color</label>
                                    <div class="color-input-group">
                                        <input type="color" name="blog_header_background_gradient_end" id="header_gradient_end" value="<?php echo htmlspecialchars(getSettingValue('blog_header_background_gradient_end', '#2c4a3b')); ?>" onchange="updateHeaderPreview()">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_header_background_gradient_end', '#2c4a3b')); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="header_solid_options" style="display: <?php echo getSettingValue('blog_header_background_type', 'gradient') == 'solid' ? 'block' : 'none'; ?>;">
                                <div class="form-group">
                                    <label>Background Color</label>
                                    <div class="color-input-group">
                                        <input type="color" name="blog_header_background_color" id="header_solid_color" value="<?php echo htmlspecialchars(getSettingValue('blog_header_background_color', '#4a7c59')); ?>" onchange="updateHeaderPreview()">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_header_background_color', '#4a7c59')); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="header_image_options" style="display: <?php echo getSettingValue('blog_header_background_type', 'gradient') == 'image' ? 'block' : 'none'; ?>;" class="full-width">
                                <div class="form-group">
                                    <label>Background Image</label>
                                    <div class="file-upload">
                                        <input type="file" name="blog_header_background_image" id="header_image" accept="image/*" onchange="previewHeaderImage(this)">
                                        <label for="header_image" class="file-upload-label">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span>Choose Background Image</span>
                                        </label>
                                        <?php $header_image = getSettingValue('blog_header_background_image'); ?>
                                        <?php if (!empty($header_image)): ?>
                                        <img src="../<?php echo htmlspecialchars($header_image); ?>" class="preview-image" id="header_image_preview" alt="Preview">
                                        <div style="margin-top: 10px;">
                                            <button type="button" class="btn btn-warning" onclick="document.getElementById('remove_header_image').value='1'; this.form.submit();">
                                                <i class="fas fa-trash"></i> Remove Image
                                            </button>
                                        </div>
                                        <input type="hidden" name="remove_header_image" id="remove_header_image" value="0">
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-info">Recommended size: 1920x400px. Max 5MB. JPG, PNG, WebP</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_header_text_color" id="header_text_color" value="<?php echo htmlspecialchars(getSettingValue('blog_header_text_color', '#ffffff')); ?>" onchange="updateHeaderPreview()">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_header_text_color', '#ffffff')); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Header Live Preview -->
                        <div class="preview-section">
                            <div class="preview-title"><i class="fas fa-eye"></i> Header Live Preview</div>
                            <p style="margin: 0 0 12px; color: #666; font-size: 13px;">Blog page shows the title below. Post pages use the same background with the article title.</p>
                            <div id="header_preview" class="blog-header-preview" style="background: linear-gradient(135deg, <?php echo getSettingValue('blog_header_background_gradient_start', '#4a7c59'); ?>, <?php echo getSettingValue('blog_header_background_gradient_end', '#2c4a3b'); ?>); color: <?php echo getSettingValue('blog_header_text_color', '#ffffff'); ?>;">
                                <h1 id="preview_title"><?php echo htmlspecialchars(getSettingValue('blog_header_title', 'The Blog')); ?></h1>
                                <p id="preview_subtitle"><?php echo htmlspecialchars(getSettingValue('blog_header_subtitle', 'Thoughts, stories, and experiences from the everyday. Honest and unfiltered.')); ?></p>
                            </div>
                        </div>
                        
                        <!-- Blog Grid Settings -->
                        <h3 class="section-title"><i class="fas fa-th-large"></i> Blog Grid Settings</h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Grid Title</label>
                                <input type="text" name="blog_grid_title" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_grid_title', 'Latest Articles')); ?>">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Grid Subtitle</label>
                                <input type="text" name="blog_grid_subtitle" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_grid_subtitle', 'Discover stories, thinking, and expertise from writers on any topic.')); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Posts Per Page</label>
                                <input type="number" name="blog_per_page" class="form-control" min="3" max="30" value="<?php echo htmlspecialchars(getSettingValue('blog_per_page', '9')); ?>">
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="blog_sidebar_enabled" id="blog_sidebar_enabled" <?php echo getSettingValue('blog_sidebar_enabled', '1') == '1' ? 'checked' : ''; ?>>
                                    <label for="blog_sidebar_enabled">Enable Sidebar</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="blog_featured_enabled" id="blog_featured_enabled" <?php echo getSettingValue('blog_featured_enabled', '1') == '1' ? 'checked' : ''; ?>>
                                    <label for="blog_featured_enabled">Show Featured Post</label>
                                </div>
                            </div>

                            <div class="form-group full-width">
                                <label>Author Display Name</label>
                                <input type="text" name="blog_author_name" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_author_name', 'COMANDA1')); ?>" placeholder="COMANDA1">
                                <div class="file-info">Shown in the &ldquo;The Author&rdquo; box on individual blog post pages.</div>
                            </div>

                            <div class="form-group full-width">
                                <label>Author Thumbnail</label>
                                <div class="file-upload">
                                    <input type="file" name="blog_author_thumbnail_upload" id="blog_author_thumbnail_upload" accept="image/*">
                                    <label for="blog_author_thumbnail_upload" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Choose Author Thumbnail</span>
                                    </label>
                                    <?php $blog_author_thumbnail_image = getSettingValue('blog_author_thumbnail_image'); ?>
                                    <?php if (!empty($blog_author_thumbnail_image)): ?>
                                    <img src="../<?php echo htmlspecialchars($blog_author_thumbnail_image); ?>" class="author-thumbnail-preview" id="blog_author_thumbnail_preview" alt="Author Thumbnail Preview">
                                    <div style="margin-top: 10px;">
                                        <button type="button" class="btn btn-warning" onclick="document.getElementById('remove_author_thumbnail').value='1'; this.form.submit();">
                                            <i class="fas fa-trash"></i> Remove Author Thumbnail
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    <input type="hidden" name="blog_author_thumbnail_image" value="<?php echo htmlspecialchars($blog_author_thumbnail_image ?? ''); ?>">
                                    <input type="hidden" name="remove_author_thumbnail" id="remove_author_thumbnail" value="0">
                                </div>
                                <div class="file-info">Square crop works best (e.g. 400&times;400px). Used only in &ldquo;The Author&rdquo; sidebar on blog posts.</div>
                                <?php $blog_author_profile_image = getSettingValue('blog_author_profile_image'); ?>
                                <input type="hidden" name="blog_author_profile_image" value="<?php echo htmlspecialchars($blog_author_profile_image ?? ''); ?>">
                                <input type="hidden" name="remove_author_image" id="remove_author_image" value="0">
                            </div>
                        </div>
                        
                        <!-- Newsletter Section -->
                        <h3 class="section-title"><i class="fas fa-envelope"></i> Newsletter Section</h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="blog_newsletter_enabled" id="blog_newsletter_enabled" <?php echo getSettingValue('blog_newsletter_enabled', '1') == '1' ? 'checked' : ''; ?> onchange="toggleNewsletterPreview()">
                                    <label for="blog_newsletter_enabled">Enable Newsletter Section</label>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Newsletter Title</label>
                                <input type="text" name="blog_newsletter_title" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_title', 'Never Miss a Post')); ?>" oninput="updateNewsletterPreview()">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Newsletter Subtitle</label>
                                <input type="text" name="blog_newsletter_subtitle" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_subtitle', 'Subscribe to get the latest articles and audio messages delivered to your inbox.')); ?>" oninput="updateNewsletterPreview()">
                            </div>
                            
                            <div class="form-group">
                                <label>Background Type</label>
                                <select name="blog_newsletter_background_type" id="newsletter_bg_type" class="form-control" onchange="toggleNewsletterBackground()">
                                    <option value="gradient" <?php echo getSettingValue('blog_newsletter_background_type', 'gradient') == 'gradient' ? 'selected' : ''; ?>>Gradient</option>
                                    <option value="solid" <?php echo getSettingValue('blog_newsletter_background_type') == 'solid' ? 'selected' : ''; ?>>Solid Color</option>
                                    <option value="image" <?php echo getSettingValue('blog_newsletter_background_type') == 'image' ? 'selected' : ''; ?>>Background Image</option>
                                </select>
                            </div>
                            
                            <div id="newsletter_gradient_options" style="display: <?php echo getSettingValue('blog_newsletter_background_type', 'gradient') == 'gradient' ? 'block' : 'none'; ?>;">
                                <div class="form-group">
                                    <label>Gradient Start Color</label>
                                    <div class="color-input-group">
                                        <input type="color" name="blog_newsletter_gradient_start" id="newsletter_gradient_start" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_gradient_start', '#2563eb')); ?>" onchange="updateNewsletterPreview()">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_gradient_start', '#2563eb')); ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Gradient End Color</label>
                                    <div class="color-input-group">
                                        <input type="color" name="blog_newsletter_gradient_end" id="newsletter_gradient_end" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_gradient_end', '#4a7c59')); ?>" onchange="updateNewsletterPreview()">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_gradient_end', '#4a7c59')); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="newsletter_solid_options" style="display: <?php echo getSettingValue('blog_newsletter_background_type', 'gradient') == 'solid' ? 'block' : 'none'; ?>;">
                                <div class="form-group">
                                    <label>Background Color</label>
                                    <div class="color-input-group">
                                        <input type="color" name="blog_newsletter_background_color" id="newsletter_solid_color" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_background_color', '#4a7c59')); ?>" onchange="updateNewsletterPreview()">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_background_color', '#4a7c59')); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="newsletter_image_options" style="display: <?php echo getSettingValue('blog_newsletter_background_type', 'gradient') == 'image' ? 'block' : 'none'; ?>;" class="full-width">
                                <div class="form-group">
                                    <label>Background Image</label>
                                    <div class="file-upload">
                                        <input type="file" name="blog_newsletter_background_image" id="newsletter_image" accept="image/*" onchange="previewNewsletterImage(this)">
                                        <label for="newsletter_image" class="file-upload-label">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            <span>Choose Background Image</span>
                                        </label>
                                        <?php $newsletter_image = getSettingValue('blog_newsletter_background_image'); ?>
                                        <?php if (!empty($newsletter_image)): ?>
                                        <img src="../<?php echo htmlspecialchars($newsletter_image); ?>" class="preview-image" id="newsletter_image_preview" alt="Preview">
                                        <div style="margin-top: 10px;">
                                            <button type="button" class="btn btn-warning" onclick="document.getElementById('remove_newsletter_image').value='1'; this.form.submit();">
                                                <i class="fas fa-trash"></i> Remove Image
                                            </button>
                                        </div>
                                        <input type="hidden" name="remove_newsletter_image" id="remove_newsletter_image" value="0">
                                        <?php endif; ?>
                                    </div>
                                    <div class="file-info">Recommended size: 1920x400px. Max 5MB. JPG, PNG, WebP</div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_newsletter_text_color" id="newsletter_text_color" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_text_color', '#ffffff')); ?>" onchange="updateNewsletterPreview()">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_text_color', '#ffffff')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Button Background Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_newsletter_button_bg" id="newsletter_button_bg" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_button_bg', '#ffffff')); ?>" onchange="updateNewsletterPreview()">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_button_bg', '#ffffff')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Button Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_newsletter_button_text" id="newsletter_button_text" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_button_text', '#4a7c59')); ?>" onchange="updateNewsletterPreview()">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_newsletter_button_text', '#4a7c59')); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Newsletter Live Preview -->
                        <div class="preview-section" id="newsletter_preview_container">
                            <div class="preview-title"><i class="fas fa-eye"></i> Newsletter Live Preview</div>
                            <div id="newsletter_preview" class="newsletter-preview" style="background: linear-gradient(135deg, <?php echo getSettingValue('blog_newsletter_gradient_start', '#2563eb'); ?>, <?php echo getSettingValue('blog_newsletter_gradient_end', '#4a7c59'); ?>); color: <?php echo getSettingValue('blog_newsletter_text_color', '#ffffff'); ?>;">
                                <h2 id="newsletter_preview_title"><?php echo htmlspecialchars(getSettingValue('blog_newsletter_title', 'Never Miss a Post')); ?></h2>
                                <p id="newsletter_preview_subtitle"><?php echo htmlspecialchars(getSettingValue('blog_newsletter_subtitle', 'Subscribe to get the latest articles and audio messages delivered to your inbox.')); ?></p>
                                <div class="preview-form">
                                    <input type="email" placeholder="Enter your email" disabled>
                                    <button style="background: <?php echo getSettingValue('blog_newsletter_button_bg', '#ffffff'); ?>; color: <?php echo getSettingValue('blog_newsletter_button_text', '#4a7c59'); ?>;">Subscribe</button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Color Settings -->
                        <h3 class="section-title"><i class="fas fa-palette"></i> Color Settings</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Background Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_background_color" value="<?php echo htmlspecialchars(getSettingValue('blog_background_color', '#f9fbf9')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_background_color', '#f9fbf9')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Card Background Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_card_background" value="<?php echo htmlspecialchars(getSettingValue('blog_card_background', '#ffffff')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_card_background', '#ffffff')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Title Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_title_color" value="<?php echo htmlspecialchars(getSettingValue('blog_title_color', '#1a2a1f')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_title_color', '#1a2a1f')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_text_color" value="<?php echo htmlspecialchars(getSettingValue('blog_text_color', '#6c757d')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_text_color', '#6c757d')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Meta Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_meta_color" value="<?php echo htmlspecialchars(getSettingValue('blog_meta_color', '#6c757d')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_meta_color', '#6c757d')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Category Background</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_category_background" value="<?php echo htmlspecialchars(getSettingValue('blog_category_background', '#4a7c59')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_category_background', '#4a7c59')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Category Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_category_color" value="<?php echo htmlspecialchars(getSettingValue('blog_category_color', '#ffffff')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_category_color', '#ffffff')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Button Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_button_color" value="<?php echo htmlspecialchars(getSettingValue('blog_button_color', '#4a7c59')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_button_color', '#4a7c59')); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Button Hover Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_button_hover_color" value="<?php echo htmlspecialchars(getSettingValue('blog_button_hover_color', '#2c4a3b')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_button_hover_color', '#2c4a3b')); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Blog Settings
                            </button>
                            <button type="button" class="btn btn-danger" onclick="resetSettings()">
                                <i class="fas fa-undo"></i> Reset to Default
                            </button>
                        </div>
                    </form>
                    
                    <form method="POST" id="resetForm" style="display: none;">
                        <input type="hidden" name="action" value="reset_blog_settings">
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Header Preview Functions
        function updateHeaderPreview() {
            const bgType = document.getElementById('header_bg_type').value;
            const textColor = document.getElementById('header_text_color').value;
            const title = document.querySelector('[name="blog_header_title"]').value;
            const subtitle = document.querySelector('[name="blog_header_subtitle"]').value;
            const preview = document.getElementById('header_preview');
            const previewTitle = document.getElementById('preview_title');
            const previewSubtitle = document.getElementById('preview_subtitle');
            
            previewTitle.textContent = title || 'The Blog';
            previewSubtitle.textContent = subtitle || 'Thoughts, stories, and experiences...';
            preview.style.color = textColor;
            previewTitle.style.color = textColor;
            previewSubtitle.style.color = textColor;
            
            if (bgType === 'gradient') {
                const startColor = document.getElementById('header_gradient_start').value;
                const endColor = document.getElementById('header_gradient_end').value;
                preview.style.background = `linear-gradient(135deg, ${startColor}, ${endColor})`;
                preview.style.backgroundImage = 'none';
            } else if (bgType === 'solid') {
                const solidColor = document.getElementById('header_solid_color').value;
                preview.style.background = solidColor;
                preview.style.backgroundImage = 'none';
            } else if (bgType === 'image') {
                const imageInput = document.getElementById('header_image');
                const currentImage = document.getElementById('header_image_preview');
                if (currentImage && currentImage.src && currentImage.src !== window.location.href) {
                    preview.style.background = `url(${currentImage.src}) center/cover no-repeat`;
                } else if (imageInput.files.length > 0) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.style.background = `url(${e.target.result}) center/cover no-repeat`;
                    };
                    reader.readAsDataURL(imageInput.files[0]);
                }
            }
        }
        
        function toggleHeaderBackground() {
            const type = document.getElementById('header_bg_type').value;
            document.getElementById('header_gradient_options').style.display = type === 'gradient' ? 'block' : 'none';
            document.getElementById('header_solid_options').style.display = type === 'solid' ? 'block' : 'none';
            document.getElementById('header_image_options').style.display = type === 'image' ? 'block' : 'none';
            updateHeaderPreview();
        }
        
        function previewHeaderImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('header_preview');
                    preview.style.background = `url(${e.target.result}) center/cover no-repeat`;
                    const imgPreview = document.getElementById('header_image_preview');
                    if (imgPreview) {
                        imgPreview.src = e.target.result;
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Newsletter Preview Functions
        function updateNewsletterPreview() {
            const bgType = document.getElementById('newsletter_bg_type').value;
            const textColor = document.getElementById('newsletter_text_color').value;
            const title = document.querySelector('[name="blog_newsletter_title"]').value;
            const subtitle = document.querySelector('[name="blog_newsletter_subtitle"]').value;
            const buttonBg = document.getElementById('newsletter_button_bg').value;
            const buttonText = document.getElementById('newsletter_button_text').value;
            const preview = document.getElementById('newsletter_preview');
            const previewTitle = document.getElementById('newsletter_preview_title');
            const previewSubtitle = document.getElementById('newsletter_preview_subtitle');
            const button = preview.querySelector('button');
            
            previewTitle.textContent = title || 'Never Miss a Post';
            previewSubtitle.textContent = subtitle || 'Subscribe to get the latest articles...';
            preview.style.color = textColor;
            previewTitle.style.color = textColor;
            previewSubtitle.style.color = textColor;
            
            if (button) {
                button.style.background = buttonBg;
                button.style.color = buttonText;
            }
            
            if (bgType === 'gradient') {
                const startColor = document.getElementById('newsletter_gradient_start').value;
                const endColor = document.getElementById('newsletter_gradient_end').value;
                preview.style.background = `linear-gradient(135deg, ${startColor}, ${endColor})`;
                preview.style.backgroundImage = 'none';
            } else if (bgType === 'solid') {
                const solidColor = document.getElementById('newsletter_solid_color').value;
                preview.style.background = solidColor;
                preview.style.backgroundImage = 'none';
            } else if (bgType === 'image') {
                const imageInput = document.getElementById('newsletter_image');
                const currentImage = document.getElementById('newsletter_image_preview');
                if (currentImage && currentImage.src && currentImage.src !== window.location.href) {
                    preview.style.background = `url(${currentImage.src}) center/cover no-repeat`;
                } else if (imageInput.files.length > 0) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.style.background = `url(${e.target.result}) center/cover no-repeat`;
                    };
                    reader.readAsDataURL(imageInput.files[0]);
                }
            }
        }
        
        function toggleNewsletterBackground() {
            const type = document.getElementById('newsletter_bg_type').value;
            document.getElementById('newsletter_gradient_options').style.display = type === 'gradient' ? 'block' : 'none';
            document.getElementById('newsletter_solid_options').style.display = type === 'solid' ? 'block' : 'none';
            document.getElementById('newsletter_image_options').style.display = type === 'image' ? 'block' : 'none';
            updateNewsletterPreview();
        }
        
        function previewNewsletterImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('newsletter_preview');
                    preview.style.background = `url(${e.target.result}) center/cover no-repeat`;
                    const imgPreview = document.getElementById('newsletter_image_preview');
                    if (imgPreview) {
                        imgPreview.src = e.target.result;
                    }
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function toggleNewsletterPreview() {
            const enabled = document.getElementById('blog_newsletter_enabled').checked;
            const container = document.getElementById('newsletter_preview_container');
            if (container) {
                container.style.display = enabled ? 'block' : 'none';
            }
        }
        
        function resetSettings() {
            if (confirm('Are you sure you want to reset all blog settings to default? This cannot be undone.')) {
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            toggleHeaderBackground();
            toggleNewsletterBackground();
            toggleNewsletterPreview();
            updateHeaderPreview();
            updateNewsletterPreview();
            
            // Add event listeners
            const headerInputs = ['blog_header_title', 'blog_header_subtitle', 'blog_header_text_color'];
            headerInputs.forEach(input => {
                const element = document.querySelector(`[name="${input}"]`);
                if (element) {
                    element.addEventListener('input', updateHeaderPreview);
                }
            });
            
            const newsletterInputs = ['blog_newsletter_title', 'blog_newsletter_subtitle', 'blog_newsletter_text_color', 'blog_newsletter_button_bg', 'blog_newsletter_button_text'];
            newsletterInputs.forEach(input => {
                const element = document.querySelector(`[name="${input}"]`);
                if (element) {
                    element.addEventListener('input', updateNewsletterPreview);
                }
            });
        });
    </script>
</body>
</html>
