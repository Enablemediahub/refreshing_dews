<?php
/**
 * About Page Settings - Admin Panel
 * Full control over about page content, design, and styling
 */

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

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_about_settings'])) {
    
    // About Page Content Settings
    $settings = [
        // Hero Section
        'about_title' => trim($_POST['about_title'] ?? 'About Me'),
        'about_subtitle' => trim($_POST['about_subtitle'] ?? 'Writer, Creator, Storyteller'),
        'about_hero_image' => trim($_POST['about_hero_image'] ?? ''),
        'about_hero_text_color' => trim($_POST['about_hero_text_color'] ?? '#ffffff'),
        
        // Profile Section
        'about_profile_image' => trim($_POST['about_profile_image'] ?? ''),
        'about_name' => trim($_POST['about_name'] ?? ''),
        'about_role' => trim($_POST['about_role'] ?? ''),
        
        // Bio Content
        'about_bio' => trim($_POST['about_bio'] ?? ''),
        'about_long_bio' => trim($_POST['about_long_bio'] ?? ''),
        
        // Signature
        'about_signature' => trim($_POST['about_signature'] ?? 'With gratitude,'),
        'about_signature_name' => trim($_POST['about_signature_name'] ?? ''),
        
        // CTA Section
        'about_cta_enabled' => isset($_POST['about_cta_enabled']) ? '1' : '0',
        
        // Color Settings
        'about_background_color' => trim($_POST['about_background_color'] ?? '#ffffff'),
        'about_text_color' => trim($_POST['about_text_color'] ?? '#1a2a1f'),
        'about_accent_color' => trim($_POST['about_accent_color'] ?? '#4a7c59'),
        
        // Hero Background Settings
        'about_hero_background_type' => trim($_POST['about_hero_background_type'] ?? 'gradient'),
        'about_hero_background_solid' => trim($_POST['about_hero_background_solid'] ?? '#4a7c59'),
        'about_hero_background_gradient_start' => trim($_POST['about_hero_background_gradient_start'] ?? '#4a7c59'),
        'about_hero_background_gradient_end' => trim($_POST['about_hero_background_gradient_end'] ?? '#2c4a3b'),
        'about_hero_background_image' => trim($_POST['about_hero_background_image'] ?? ''),
        'about_hero_background_overlay' => trim($_POST['about_hero_background_overlay'] ?? '0.6'),
        
        // Typography Settings
        'about_heading_font' => trim($_POST['about_heading_font'] ?? 'Playfair Display'),
        'about_body_font' => trim($_POST['about_body_font'] ?? 'Inter'),
        'about_heading_size' => trim($_POST['about_heading_size'] ?? '42'),
        'about_body_size' => trim($_POST['about_body_size'] ?? '16'),
    ];
    
    // Save each setting
    foreach ($settings as $key => $value) {
        updateSetting($key, $value);
    }
    
    // Handle file upload for hero background image
    if (isset($_FILES['about_hero_bg_image']) && $_FILES['about_hero_bg_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadAboutImage($_FILES['about_hero_bg_image'], 'hero');
        if ($upload_result['success']) {
            updateSetting('about_hero_background_image', $upload_result['filename']);
            updateSetting('about_hero_background_type', 'image');
            $success_message = 'Settings updated successfully! Hero background image uploaded.';
        } else {
            $error_message = 'Settings updated but hero image upload failed: ' . $upload_result['error'];
        }
    }

    if (isset($_POST['remove_hero_image']) && $_POST['remove_hero_image'] === '1') {
        updateSetting('about_hero_background_image', '');
        $success_message = $success_message ?: 'Hero background image removed successfully!';
    }
    
    // Handle file upload for profile image
    if (isset($_FILES['about_profile_image_upload']) && $_FILES['about_profile_image_upload']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadAboutImage($_FILES['about_profile_image_upload'], 'profile');
        if ($upload_result['success']) {
            updateSetting('about_profile_image', $upload_result['filename']);
            $success_message = $success_message ?: 'Settings updated successfully! Profile image uploaded.';
        } else {
            $error_message = $error_message ?: 'Profile image upload failed: ' . $upload_result['error'];
        }
    }
    
    if (empty($error_message) && empty($success_message)) {
        $success_message = 'About page settings updated successfully!';
    }
    
    logAdminAction('update_about_settings', 'Updated about page settings');
}

// Helper function to upload about page images
function uploadAboutImage($file, $type = 'hero') {
    $target_dir = "../uploads/about/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        return ['success' => false, 'error' => 'File is not an image.'];
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5000000) {
        return ['success' => false, 'error' => 'Sorry, your file is too large. Max 5MB.'];
    }
    
    // Allow certain file formats
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($imageFileType, $allowed_types)) {
        return ['success' => false, 'error' => 'Sorry, only JPG, JPEG, PNG, GIF & WEBP files are allowed.'];
    }
    
    // Generate unique filename
    $unique_id = uniqid() . '_' . time();
    $new_filename = $unique_id . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return ['success' => true, 'filename' => 'uploads/about/' . $new_filename];
    } else {
        return ['success' => false, 'error' => 'Sorry, there was an error uploading your file.'];
    }
}

// Get current settings
$about_title = getSetting('about_title', 'About Me');
$about_subtitle = getSetting('about_subtitle', 'Writer, Creator, Storyteller');
$about_hero_image = getSetting('about_hero_image', '');
$about_hero_text_color = getSetting('about_hero_text_color', '#ffffff');
$about_profile_image = getSetting('about_profile_image', '');
$about_name = getSetting('about_name', '');
$about_role = getSetting('about_role', '');
$about_bio = getSetting('about_bio', '');
$about_long_bio = getSetting('about_long_bio', '');
$about_signature = getSetting('about_signature', 'With gratitude,');
$about_signature_name = getSetting('about_signature_name', '');
$about_cta_enabled = getSetting('about_cta_enabled', '1');
$about_background_color = getSetting('about_background_color', '#ffffff');
$about_text_color = getSetting('about_text_color', '#1a2a1f');
$about_accent_color = getSetting('about_accent_color', '#4a7c59');
$about_hero_background_type = getSetting('about_hero_background_type', 'gradient');
$about_hero_background_solid = getSetting('about_hero_background_solid', '#4a7c59');
$about_hero_background_gradient_start = getSetting('about_hero_background_gradient_start', '#4a7c59');
$about_hero_background_gradient_end = getSetting('about_hero_background_gradient_end', '#2c4a3b');
$about_hero_background_image = getSetting('about_hero_background_image', '');
$about_hero_background_overlay = getSetting('about_hero_background_overlay', '0.6');
$about_heading_font = getSetting('about_heading_font', 'Playfair Display');
$about_body_font = getSetting('about_body_font', 'Inter');
$about_heading_size = getSetting('about_heading_size', '42');
$about_body_size = getSetting('about_body_size', '16');

// Initial hero preview background for live preview panel
$about_preview_style = '';
if ($about_hero_background_type === 'solid') {
    $about_preview_style = 'background: ' . $about_hero_background_solid . ';';
} elseif ($about_hero_background_type === 'image' && !empty($about_hero_background_image)) {
    $overlay = is_numeric($about_hero_background_overlay) ? max(0, min(1, (float) $about_hero_background_overlay)) : 0.6;
    $about_preview_style = "background: linear-gradient(rgba(0,0,0,{$overlay}), rgba(0,0,0,{$overlay})), url('../{$about_hero_background_image}') center/cover no-repeat;";
} else {
    $about_preview_style = "background: linear-gradient(135deg, {$about_hero_background_gradient_start}, {$about_hero_background_gradient_end});";
}

// Get site settings for header
$site_title = getSetting('site_title', 'Refreshing Dews');
$site_logo = getSetting('site_logo', 'assets/logo/refreshing-dews-logo.png');
$primary_color = getSetting('primary_color', '#4a7c59');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>About Page Settings - Admin Panel</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <!-- SimpleMDE Markdown Editor CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/simplemde/latest/simplemde.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
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
        
        /* Sidebar Styles - Mobile First */
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
            max-width: 120px;
            margin-bottom: 12px;
            background: white;
            padding: 8px;
            border-radius: 10px;
        }
        
        .sidebar-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
        }
        
        .sidebar-header p {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-top: 3px;
        }
        
        .sidebar-menu {
            padding: 20px 0 40px;
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
            cursor: pointer;
        }
        
        .sidebar-menu-item i {
            width: 20px;
            font-size: 18px;
        }
        
        .sidebar-menu-item:hover,
        .sidebar-menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: <?php echo $primary_color; ?>;
        }
        
        .sidebar-menu-label {
            padding: 10px 25px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
        }
        
        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 15px 20px;
        }
        
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1500;
            background: <?php echo $primary_color; ?>;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
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
            backdrop-filter: blur(2px);
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
                padding: 80px 20px 20px;
            }
        }
        
        @media (max-width: 768px) {
            .admin-main {
                padding: 80px 15px 15px;
            }
        }
        
        .top-nav {
            background: white;
            padding: 15px 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .top-nav {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
        }
        
        .top-nav-title h1 {
            font-size: 22px;
            font-weight: 600;
            color: #333;
        }
        
        .top-nav-title p {
            color: #666;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .top-nav-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .user-role {
            font-size: 11px;
            color: #666;
        }
        
        .user-avatar {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, <?php echo $primary_color; ?>, #2c4a3b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .form-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .form-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            background: #fafbfc;
        }
        
        .form-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-header h2 i {
            color: <?php echo $primary_color; ?>;
        }
        
        .form-header p {
            font-size: 13px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .form-body {
            padding: 25px;
        }
        
        @media (max-width: 768px) {
            .form-header {
                padding: 15px 20px;
            }
            .form-body {
                padding: 20px;
            }
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #dc3545;
            margin-left: 3px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: <?php echo $primary_color; ?>;
            box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        .image-upload-area {
            border: 2px dashed #e9ecef;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: #fafbfc;
        }
        
        .image-upload-area:hover {
            border-color: <?php echo $primary_color; ?>;
            background: rgba(74, 124, 89, 0.02);
        }
        
        .image-upload-area i {
            font-size: 32px;
            color: #adb5bd;
            margin-bottom: 8px;
        }
        
        .image-upload-area p {
            color: #6c757d;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .image-upload-area small {
            color: #adb5bd;
            font-size: 10px;
        }
        
        .current-image-preview {
            margin-top: 12px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .current-image-preview img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            margin-top: 8px;
        }
        
        .toggle-switch {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .toggle-switch input {
            width: 44px;
            height: 24px;
            appearance: none;
            background: #e9ecef;
            border-radius: 24px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .toggle-switch input:checked {
            background: <?php echo $primary_color; ?>;
        }
        
        .toggle-switch input::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }
        
        .toggle-switch input:checked::before {
            transform: translateX(20px);
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: <?php echo $primary_color; ?>;
            color: white;
        }
        
        .btn-primary:hover,
        .btn-primary:active {
            background: #2c4a3b;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover,
        .btn-secondary:active {
            background: #5a6268;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            animation: slideDown 0.4s ease;
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
        
        .preview-box {
            background: <?php echo $about_background_color; ?>;
            padding: 15px;
            border-radius: 12px;
            margin-top: 10px;
            border: 1px solid #e9ecef;
        }
        
        .preview-box h4 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #333;
        }

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
            border-color: <?php echo $primary_color; ?>;
            background: #f1f8f1;
        }

        .file-upload-label i {
            color: <?php echo $primary_color; ?>;
            font-size: 20px;
        }

        .file-info {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .preview-image {
            width: 120px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }

        .preview-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 24px;
        }

        .preview-title {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .about-header-preview {
            padding: 60px 20px;
            text-align: center;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            min-height: 180px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .about-header-preview h1 {
            font-family: 'Playfair Display', serif;
            font-size: 36px;
            margin-bottom: 12px;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.25);
        }

        .about-header-preview p {
            font-size: 16px;
            max-width: 600px;
            margin: 0 auto;
            opacity: 0.95;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-warning:hover,
        .btn-warning:active {
            background: #e0a800;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (hover: none) and (pointer: coarse) {
            .btn:active,
            .image-upload-area:active {
                transform: scale(0.98);
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="admin-wrapper">
        <?php $current_page = basename(__FILE__); include __DIR__ . '/includes/sidebar.php'; ?>
        <div class="admin-main">
            <div class="top-nav">
                <div class="top-nav-title">
                    <h1>About Page Settings</h1>
                    <p>Customize your About page content and design</p>
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
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="update_about_settings" value="1">
                
                <!-- Hero Section Settings -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-image"></i> Hero Section</h2>
                        <p>Configure the top banner of your About page</p>
                    </div>
                    <div class="form-body">
                        <div class="form-group">
                            <label for="about_title">Page Title</label>
                            <input type="text" class="form-control" id="about_title" name="about_title" 
                                   value="<?php echo htmlspecialchars($about_title); ?>"
                                   placeholder="About Me" oninput="updateAboutHeaderPreview()">
                        </div>
                        
                        <div class="form-group">
                            <label for="about_subtitle">Page Subtitle</label>
                            <input type="text" class="form-control" id="about_subtitle" name="about_subtitle" 
                                   value="<?php echo htmlspecialchars($about_subtitle); ?>"
                                   placeholder="Writer, Creator, Storyteller" oninput="updateAboutHeaderPreview()">
                        </div>
                        
                        <div class="form-group">
                            <label for="about_hero_text_color">Hero Text Color</label>
                            <div class="form-row">
                                <div style="flex: 1;">
                                    <input type="color" class="form-control" id="about_hero_text_color" name="about_hero_text_color" 
                                           value="<?php echo htmlspecialchars($about_hero_text_color); ?>"
                                           style="height: 50px; padding: 5px;" onchange="updateAboutHeaderPreview()">
                                </div>
                                <div style="flex: 1;">
                                    <div class="color-preview" id="hero_text_color_preview" style="background: <?php echo $about_hero_text_color; ?>;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Hero Background Type</label>
                            <div class="form-row">
                                <div class="status-toggle" style="display: flex; gap: 10px;">
                                    <div class="status-option" style="flex: 1;">
                                        <input type="radio" id="bg_gradient" name="about_hero_background_type" value="gradient" <?php echo $about_hero_background_type == 'gradient' ? 'checked' : ''; ?>>
                                        <label for="bg_gradient" style="display: block; padding: 10px; text-align: center; background: #f8f9fa; border-radius: 10px; cursor: pointer;">
                                            <i class="fas fa-chart-line"></i> Gradient
                                        </label>
                                    </div>
                                    <div class="status-option" style="flex: 1;">
                                        <input type="radio" id="bg_solid" name="about_hero_background_type" value="solid" <?php echo $about_hero_background_type == 'solid' ? 'checked' : ''; ?>>
                                        <label for="bg_solid" style="display: block; padding: 10px; text-align: center; background: #f8f9fa; border-radius: 10px; cursor: pointer;">
                                            <i class="fas fa-palette"></i> Solid
                                        </label>
                                    </div>
                                    <div class="status-option" style="flex: 1;">
                                        <input type="radio" id="bg_image" name="about_hero_background_type" value="image" <?php echo $about_hero_background_type == 'image' ? 'checked' : ''; ?>>
                                        <label for="bg_image" style="display: block; padding: 10px; text-align: center; background: #f8f9fa; border-radius: 10px; cursor: pointer;">
                                            <i class="fas fa-image"></i> Image
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="gradientSettings" style="display: <?php echo $about_hero_background_type == 'gradient' ? 'block' : 'none'; ?>;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="about_hero_gradient_start">Gradient Start Color</label>
                                    <input type="color" class="form-control" id="about_hero_gradient_start" name="about_hero_background_gradient_start" 
                                           value="<?php echo htmlspecialchars($about_hero_background_gradient_start); ?>"
                                           style="height: 45px;" onchange="updateAboutHeaderPreview()">
                                </div>
                                <div class="form-group">
                                    <label for="about_hero_gradient_end">Gradient End Color</label>
                                    <input type="color" class="form-control" id="about_hero_gradient_end" name="about_hero_background_gradient_end" 
                                           value="<?php echo htmlspecialchars($about_hero_background_gradient_end); ?>"
                                           style="height: 45px;" onchange="updateAboutHeaderPreview()">
                                </div>
                            </div>
                        </div>
                        
                        <div id="solidSettings" style="display: <?php echo $about_hero_background_type == 'solid' ? 'block' : 'none'; ?>;">
                            <div class="form-group">
                                <label for="about_hero_solid_color">Solid Background Color</label>
                                <input type="color" class="form-control" id="about_hero_solid_color" name="about_hero_background_solid" 
                                       value="<?php echo htmlspecialchars($about_hero_background_solid); ?>"
                                       style="height: 45px;" onchange="updateAboutHeaderPreview()">
                            </div>
                        </div>
                        
                        <div id="imageSettings" style="display: <?php echo $about_hero_background_type == 'image' ? 'block' : 'none'; ?>;">
                            <div class="form-group">
                                <label>Hero Background Image</label>
                                <div class="file-upload">
                                    <input type="file" name="about_hero_bg_image" id="about_hero_bg_image" accept="image/*" onchange="previewAboutHeaderImage(this)">
                                    <label for="about_hero_bg_image" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Choose Background Image</span>
                                    </label>
                                    <?php if (!empty($about_hero_background_image)): ?>
                                    <img src="../<?php echo htmlspecialchars($about_hero_background_image); ?>" class="preview-image" id="about_header_image_preview" alt="Hero preview">
                                    <div style="width: 100%; margin-top: 10px;">
                                        <button type="button" class="btn btn-warning" onclick="document.getElementById('remove_hero_image').value='1'; this.form.submit();">
                                            <i class="fas fa-trash"></i> Remove Image
                                        </button>
                                    </div>
                                    <input type="hidden" name="remove_hero_image" id="remove_hero_image" value="0">
                                    <?php else: ?>
                                    <img src="" class="preview-image" id="about_header_image_preview" alt="Hero preview" style="display: none;">
                                    <?php endif; ?>
                                </div>
                                <div class="file-info">Recommended size: 1920x600px. Max 5MB. JPG, PNG, WebP</div>
                            </div>
                            <div class="form-group">
                                <label for="about_hero_background_overlay">Overlay Opacity (0-1)</label>
                                <input type="range" class="form-control" id="about_hero_background_overlay" name="about_hero_background_overlay" 
                                       value="<?php echo htmlspecialchars($about_hero_background_overlay); ?>"
                                       min="0" max="1" step="0.1" style="padding: 0;" oninput="updateAboutHeaderPreview()">
                                <small>Controls the dark overlay on the background image</small>
                            </div>
                        </div>

                        <!-- Header Live Preview -->
                        <div class="preview-section">
                            <div class="preview-title"><i class="fas fa-eye"></i> Header Live Preview</div>
                            <div id="about_header_preview" class="about-header-preview" style="<?php echo $about_preview_style; ?> color: <?php echo htmlspecialchars($about_hero_text_color); ?>;">
                                <h1 id="about_preview_title"><?php echo htmlspecialchars($about_title); ?></h1>
                                <p id="about_preview_subtitle"><?php echo htmlspecialchars($about_subtitle); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Section -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-user-circle"></i> Profile Section</h2>
                        <p>Your personal information and photo</p>
                    </div>
                    <div class="form-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="about_name">Your Name</label>
                                <input type="text" class="form-control" id="about_name" name="about_name" 
                                       value="<?php echo htmlspecialchars($about_name); ?>"
                                       placeholder="Sarah Johnson">
                            </div>
                            <div class="form-group">
                                <label for="about_role">Your Role/Title</label>
                                <input type="text" class="form-control" id="about_role" name="about_role" 
                                       value="<?php echo htmlspecialchars($about_role); ?>"
                                       placeholder="Founder & Content Creator">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Profile Photo</label>
                            <div class="image-upload-area" id="profileImageUpload">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>Click to upload or drag and drop</p>
                                <small>JPG, PNG, WEBP up to 5MB</small>
                                <input type="file" id="profile_image_upload" name="about_profile_image_upload" accept="image/*" style="display: none;">
                            </div>
                            <?php if (!empty($about_profile_image)): ?>
                            <div class="current-image-preview">
                                <img src="../<?php echo htmlspecialchars($about_profile_image); ?>" alt="Current profile photo">
                                <div>
                                    <p style="font-size: 12px; color: #666;">Current photo: <?php echo basename($about_profile_image); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            <input type="hidden" name="about_profile_image" value="<?php echo htmlspecialchars($about_profile_image); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Bio Content -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-align-left"></i> Bio Content</h2>
                        <p>Tell your story to your audience</p>
                    </div>
                    <div class="form-body">
                        <div class="form-group">
                            <label for="about_bio">Short Bio</label>
                            <textarea class="form-control" id="about_bio" name="about_bio" rows="3"><?php echo htmlspecialchars($about_bio); ?></textarea>
                            <small>Brief introduction that appears at the top</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="about_long_bio">Long Bio / Full Story</label>
                            <textarea id="about_long_bio" name="about_long_bio" rows="8"><?php echo htmlspecialchars($about_long_bio); ?></textarea>
                            <small>Use Markdown for formatting. This will appear as your full story.</small>
                        </div>
                    </div>
                </div>
                
                <!-- Signature -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-signature"></i> Signature</h2>
                        <p>Personal signature at the end of your bio</p>
                    </div>
                    <div class="form-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="about_signature">Signature Message</label>
                                <input type="text" class="form-control" id="about_signature" name="about_signature" 
                                       value="<?php echo htmlspecialchars($about_signature); ?>"
                                       placeholder="With gratitude,">
                            </div>
                            <div class="form-group">
                                <label for="about_signature_name">Signature Name</label>
                                <input type="text" class="form-control" id="about_signature_name" name="about_signature_name" 
                                       value="<?php echo htmlspecialchars($about_signature_name); ?>"
                                       placeholder="Sarah">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Color & Typography Settings -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-palette"></i> Design Settings</h2>
                        <p>Colors, fonts, and styling</p>
                    </div>
                    <div class="form-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="about_background_color">Page Background Color</label>
                                <input type="color" class="form-control" name="about_background_color" 
                                       value="<?php echo htmlspecialchars($about_background_color); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="about_text_color">Text Color</label>
                                <input type="color" class="form-control" name="about_text_color" 
                                       value="<?php echo htmlspecialchars($about_text_color); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="about_accent_color">Accent Color</label>
                                <input type="color" class="form-control" name="about_accent_color" 
                                       value="<?php echo htmlspecialchars($about_accent_color); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="about_heading_font">Heading Font</label>
                                <select class="form-control" name="about_heading_font">
                                    <option value="Playfair Display" <?php echo $about_heading_font == 'Playfair Display' ? 'selected' : ''; ?>>Playfair Display</option>
                                    <option value="Poppins" <?php echo $about_heading_font == 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                                    <option value="Montserrat" <?php echo $about_heading_font == 'Montserrat' ? 'selected' : ''; ?>>Montserrat</option>
                                    <option value="Roboto" <?php echo $about_heading_font == 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                    <option value="Merriweather" <?php echo $about_heading_font == 'Merriweather' ? 'selected' : ''; ?>>Merriweather</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="about_body_font">Body Font</label>
                                <select class="form-control" name="about_body_font">
                                    <option value="Inter" <?php echo $about_body_font == 'Inter' ? 'selected' : ''; ?>>Inter</option>
                                    <option value="Roboto" <?php echo $about_body_font == 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                    <option value="Open Sans" <?php echo $about_body_font == 'Open Sans' ? 'selected' : ''; ?>>Open Sans</option>
                                    <option value="Poppins" <?php echo $about_body_font == 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                                    <option value="Lato" <?php echo $about_body_font == 'Lato' ? 'selected' : ''; ?>>Lato</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="about_heading_size">Heading Size (px)</label>
                                <input type="number" class="form-control" name="about_heading_size" 
                                       value="<?php echo htmlspecialchars($about_heading_size); ?>"
                                       min="24" max="72">
                            </div>
                            <div class="form-group">
                                <label for="about_body_size">Body Text Size (px)</label>
                                <input type="number" class="form-control" name="about_body_size" 
                                       value="<?php echo htmlspecialchars($about_body_size); ?>"
                                       min="12" max="20">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- CTA Section -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-bullhorn"></i> Call to Action</h2>
                        <p>Show or hide the CTA section at the bottom</p>
                    </div>
                    <div class="form-body">
                        <div class="form-group">
                            <div class="toggle-switch">
                                <input type="checkbox" id="about_cta_enabled" name="about_cta_enabled" value="1" <?php echo $about_cta_enabled == '1' ? 'checked' : ''; ?>>
                                <label for="about_cta_enabled">Enable Call to Action Section</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                    <a href="../about.php" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> View About Page
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/simplemde/latest/simplemde.min.js"></script>
    <script>
        // Initialize SimpleMDE for long bio
        const simplemde = new SimpleMDE({
            element: document.getElementById("about_long_bio"),
            spellChecker: false,
            toolbar: ["bold", "italic", "heading", "|", "quote", "unordered-list", "ordered-list", "|", "link", "preview"],
            placeholder: "Write your full story here...",
            status: false
        });
        
        function getAboutBackgroundType() {
            if (document.getElementById('bg_gradient').checked) return 'gradient';
            if (document.getElementById('bg_solid').checked) return 'solid';
            return 'image';
        }

        function updateAboutHeaderPreview() {
            const bgType = getAboutBackgroundType();
            const textColor = document.getElementById('about_hero_text_color').value;
            const title = document.getElementById('about_title').value;
            const subtitle = document.getElementById('about_subtitle').value;
            const preview = document.getElementById('about_header_preview');
            const previewTitle = document.getElementById('about_preview_title');
            const previewSubtitle = document.getElementById('about_preview_subtitle');
            const colorPreview = document.getElementById('hero_text_color_preview');

            previewTitle.textContent = title || 'About Me';
            previewSubtitle.textContent = subtitle || 'Writer, Creator, Storyteller';
            preview.style.color = textColor;
            previewTitle.style.color = textColor;
            previewSubtitle.style.color = textColor;
            if (colorPreview) colorPreview.style.background = textColor;

            if (bgType === 'gradient') {
                const startColor = document.getElementById('about_hero_gradient_start').value;
                const endColor = document.getElementById('about_hero_gradient_end').value;
                preview.style.background = `linear-gradient(135deg, ${startColor}, ${endColor})`;
            } else if (bgType === 'solid') {
                preview.style.background = document.getElementById('about_hero_solid_color').value;
            } else if (bgType === 'image') {
                const overlay = document.getElementById('about_hero_background_overlay').value || 0.6;
                const imagePreview = document.getElementById('about_header_image_preview');
                const imageInput = document.getElementById('about_hero_bg_image');

                let imageUrl = '';
                if (imagePreview && imagePreview.src && imagePreview.style.display !== 'none') {
                    imageUrl = imagePreview.src;
                } else if (imageInput && imageInput.files.length > 0) {
                    imageUrl = URL.createObjectURL(imageInput.files[0]);
                }

                if (imageUrl) {
                    preview.style.background = `linear-gradient(rgba(0,0,0,${overlay}), rgba(0,0,0,${overlay})), url(${imageUrl}) center/cover no-repeat`;
                } else {
                    preview.style.background = `linear-gradient(135deg, ${document.getElementById('about_hero_gradient_start').value}, ${document.getElementById('about_hero_gradient_end').value})`;
                }
            }
        }

        function previewAboutHeaderImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let imgPreview = document.getElementById('about_header_image_preview');
                    if (!imgPreview) {
                        imgPreview = document.createElement('img');
                        imgPreview.id = 'about_header_image_preview';
                        imgPreview.className = 'preview-image';
                        imgPreview.alt = 'Hero preview';
                        input.parentElement.appendChild(imgPreview);
                    }
                    imgPreview.src = e.target.result;
                    imgPreview.style.display = 'block';
                    document.getElementById('bg_image').checked = true;
                    toggleBackgroundSettings();
                    updateAboutHeaderPreview();
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Toggle background settings based on selection
        const bgGradient = document.getElementById('bg_gradient');
        const bgSolid = document.getElementById('bg_solid');
        const bgImage = document.getElementById('bg_image');
        const gradientSettings = document.getElementById('gradientSettings');
        const solidSettings = document.getElementById('solidSettings');
        const imageSettings = document.getElementById('imageSettings');
        
        function toggleBackgroundSettings() {
            if (bgGradient.checked) {
                gradientSettings.style.display = 'block';
                solidSettings.style.display = 'none';
                imageSettings.style.display = 'none';
            } else if (bgSolid.checked) {
                gradientSettings.style.display = 'none';
                solidSettings.style.display = 'block';
                imageSettings.style.display = 'none';
            } else if (bgImage.checked) {
                gradientSettings.style.display = 'none';
                solidSettings.style.display = 'none';
                imageSettings.style.display = 'block';
            }
            updateAboutHeaderPreview();
        }
        
        bgGradient.addEventListener('change', toggleBackgroundSettings);
        bgSolid.addEventListener('change', toggleBackgroundSettings);
        bgImage.addEventListener('change', toggleBackgroundSettings);
        
        // Profile image upload handling
        const profileImageUpload = document.getElementById('profileImageUpload');
        const profileImageInput = document.getElementById('profile_image_upload');

        if (profileImageUpload) {
            profileImageUpload.addEventListener('click', () => profileImageInput.click());
            profileImageUpload.addEventListener('dragover', (e) => {
                e.preventDefault();
                profileImageUpload.style.borderColor = '<?php echo $primary_color; ?>';
            });
            profileImageUpload.addEventListener('dragleave', () => {
                profileImageUpload.style.borderColor = '#e9ecef';
            });
            profileImageUpload.addEventListener('drop', (e) => {
                e.preventDefault();
                profileImageUpload.style.borderColor = '#e9ecef';
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    profileImageInput.files = files;
                }
            });
        }

        updateAboutHeaderPreview();
        
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
        
        document.querySelectorAll('.sidebar-menu-item').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    setTimeout(closeSidebar, 150);
                }
            });
        });
        
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 1024 && adminSidebar.classList.contains('sidebar-open')) {
                    closeSidebar();
                }
            }, 250);
        });
        
        // Form submission loading state
        const submitBtn = document.getElementById('submitBtn');
        const form = document.querySelector('form');
        
        if (form) {
            form.addEventListener('submit', function() {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="loading"></span> Saving...';
                }
            });
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>