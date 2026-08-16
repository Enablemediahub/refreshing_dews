<?php
/**
 * Audio Page Settings - Admin Panel
 * Full control over audio page content, design, and styling
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

if (isset($_SESSION['audio_settings_flash'])) {
    $flash = $_SESSION['audio_settings_flash'];
    $success_message = $flash['success'] ?? '';
    $error_message = $flash['error'] ?? '';
    unset($_SESSION['audio_settings_flash']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_audio_settings'])) {
    
    // Audio Page Settings
    $settings = [
        // Header Section
        'audio_header_title' => trim($_POST['audio_header_title'] ?? 'Audio Messages'),
        'audio_header_subtitle' => trim($_POST['audio_header_subtitle'] ?? 'Heartfelt messages, Words of Hope from me to you.'),
        'audio_header_text_color' => trim($_POST['audio_header_text_color'] ?? '#ffffff'),
        
        // Header Background Settings
        'audio_header_background_type' => trim($_POST['audio_header_background_type'] ?? 'gradient'),
        'audio_header_background_solid' => trim($_POST['audio_header_background_solid'] ?? '#4a7c59'),
        'audio_header_background_gradient_start' => trim($_POST['audio_header_background_gradient_start'] ?? '#4a7c59'),
        'audio_header_background_gradient_end' => trim($_POST['audio_header_background_gradient_end'] ?? '#2c4a3b'),
        'audio_header_background_image' => trim($_POST['audio_header_background_image'] ?? ''),
        'audio_header_background_overlay' => trim($_POST['audio_header_background_overlay'] ?? '0.6'),
        
        // Grid Settings
        'audio_per_page' => trim($_POST['audio_per_page'] ?? '9'),
        'audio_grid_title' => trim($_POST['audio_grid_title'] ?? 'All Audio Messages'),
        'audio_grid_subtitle' => trim($_POST['audio_grid_subtitle'] ?? 'Listen to messages on various topics - inspiration, teaching, reflections, and more.'),
        
        // Color Settings
        'audio_background_color' => trim($_POST['audio_background_color'] ?? '#f9fbf9'),
        'audio_card_background' => trim($_POST['audio_card_background'] ?? '#ffffff'),
        'audio_title_color' => trim($_POST['audio_title_color'] ?? '#1a2a1f'),
        'audio_text_color' => trim($_POST['audio_text_color'] ?? '#6c757d'),
        'audio_meta_color' => trim($_POST['audio_meta_color'] ?? '#6c757d'),
        'audio_category_background' => trim($_POST['audio_category_background'] ?? '#4a7c59'),
        'audio_category_color' => trim($_POST['audio_category_color'] ?? '#ffffff'),
        'audio_duration_background' => trim($_POST['audio_duration_background'] ?? 'rgba(0,0,0,0.7)'),
        'audio_duration_color' => trim($_POST['audio_duration_color'] ?? '#ffffff'),
        
        // Button Settings
        'audio_button_color' => trim($_POST['audio_button_color'] ?? '#4a7c59'),
        'audio_button_hover_color' => trim($_POST['audio_button_hover_color'] ?? '#2c4a3b'),
        'audio_play_button_background' => trim($_POST['audio_play_button_background'] ?? '#4a7c59'),
        'audio_play_button_hover_background' => trim($_POST['audio_play_button_hover_background'] ?? '#2c4a3b'),
        'audio_play_button_color' => trim($_POST['audio_play_button_color'] ?? '#ffffff'),
        
        // Featured Audio Settings
        'audio_featured_enabled' => isset($_POST['audio_featured_enabled']) ? '1' : '0',
        'featured_audio_id' => trim($_POST['featured_audio_id'] ?? '0'),
        
        // Newsletter Settings
        'audio_newsletter_enabled' => isset($_POST['audio_newsletter_enabled']) ? '1' : '0',
        'audio_newsletter_title' => trim($_POST['audio_newsletter_title'] ?? 'Never Miss a Message'),
        'audio_newsletter_subtitle' => trim($_POST['audio_newsletter_subtitle'] ?? 'Subscribe to get notified when new audio messages are published.'),
        
        // Mini Player Settings
        'mini_player_background' => trim($_POST['mini_player_background'] ?? '#ffffff'),
        'mini_player_text_color' => trim($_POST['mini_player_text_color'] ?? '#333333'),
        'mini_player_button_background' => trim($_POST['mini_player_button_background'] ?? '#4a7c59'),
        'mini_player_button_color' => trim($_POST['mini_player_button_color'] ?? '#ffffff'),
        
        // Typography Settings
        'audio_heading_font' => trim($_POST['audio_heading_font'] ?? 'Playfair Display'),
        'audio_body_font' => trim($_POST['audio_body_font'] ?? 'Inter'),
        'audio_heading_size' => trim($_POST['audio_heading_size'] ?? '48'),
        'audio_body_size' => trim($_POST['audio_body_size'] ?? '16'),
        
        // Card Shadow Settings
        'audio_card_shadow' => trim($_POST['audio_card_shadow'] ?? '0 4px 6px rgba(0,0,0,0.05)'),
        'audio_card_hover_shadow' => trim($_POST['audio_card_hover_shadow'] ?? '0 20px 40px rgba(0,0,0,0.15)'),
    ];
    
    // Save each setting
    foreach ($settings as $key => $value) {
        updateSetting($key, $value);
    }
    
    // Handle file upload for header background image
    if (isset($_FILES['audio_header_bg_image']) && $_FILES['audio_header_bg_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadAudioImage($_FILES['audio_header_bg_image'], 'header');
        if ($upload_result['success']) {
            updateSetting('audio_header_background_image', $upload_result['filename']);
            $success_message = 'Settings updated successfully! Header background image uploaded.';
        } else {
            $error_message = 'Settings updated but header image upload failed: ' . $upload_result['error'];
        }
    }
    
    if (isset($_POST['set_featured_audio'])) {
        if ((int) ($_POST['featured_audio_id'] ?? 0) > 0) {
            $success_message = 'Featured audio message updated successfully!';
            logAdminAction('set_featured_audio', 'Set audio ID: ' . (int) $_POST['featured_audio_id'] . ' as featured');
        } else {
            $error_message = 'Please select an audio message to feature.';
        }
    } elseif (empty($error_message) && empty($success_message)) {
        $success_message = 'Audio page settings updated successfully!';
    }
    
    if (!isset($_POST['set_featured_audio'])) {
        logAdminAction('update_audio_settings', 'Updated audio page settings');
    }

    $_SESSION['audio_settings_flash'] = [
        'success' => $success_message,
        'error' => $error_message
    ];

    header('Location: audio-settings.php');
    exit();
}

// Helper function to upload audio images
function uploadAudioImage($file, $type = 'header') {
    $target_dir = "../uploads/audio/";
    
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
        return ['success' => true, 'filename' => 'uploads/audio/' . $new_filename];
    } else {
        return ['success' => false, 'error' => 'Sorry, there was an error uploading your file.'];
    }
}

// Get current settings
$audio_header_title = getSetting('audio_header_title', 'Audio Messages');
$audio_header_subtitle = getSetting('audio_header_subtitle', 'Heartfelt messages, Words of Hope from me to you.');
$audio_header_text_color = getSetting('audio_header_text_color', '#ffffff');
$audio_header_background_type = getSetting('audio_header_background_type', 'gradient');
$audio_header_background_solid = getSetting('audio_header_background_solid', '#4a7c59');
$audio_header_background_gradient_start = getSetting('audio_header_background_gradient_start', '#4a7c59');
$audio_header_background_gradient_end = getSetting('audio_header_background_gradient_end', '#2c4a3b');
$audio_header_background_image = getSetting('audio_header_background_image', '');
$audio_header_background_overlay = getSetting('audio_header_background_overlay', '0.6');
$audio_per_page = getSetting('audio_per_page', '9');
$audio_grid_title = getSetting('audio_grid_title', 'All Audio Messages');
$audio_grid_subtitle = getSetting('audio_grid_subtitle', 'Listen to messages on various topics - inspiration, teaching, reflections, and more.');
$audio_background_color = getSetting('audio_background_color', '#f9fbf9');
$audio_card_background = getSetting('audio_card_background', '#ffffff');
$audio_title_color = getSetting('audio_title_color', '#1a2a1f');
$audio_text_color = getSetting('audio_text_color', '#6c757d');
$audio_meta_color = getSetting('audio_meta_color', '#6c757d');
$audio_category_background = getSetting('audio_category_background', '#4a7c59');
$audio_category_color = getSetting('audio_category_color', '#ffffff');
$audio_duration_background = getSetting('audio_duration_background', 'rgba(0,0,0,0.7)');
$audio_duration_color = getSetting('audio_duration_color', '#ffffff');
$audio_button_color = getSetting('audio_button_color', '#4a7c59');
$audio_button_hover_color = getSetting('audio_button_hover_color', '#2c4a3b');
$audio_play_button_background = getSetting('audio_play_button_background', '#4a7c59');
$audio_play_button_hover_background = getSetting('audio_play_button_hover_background', '#2c4a3b');
$audio_play_button_color = getSetting('audio_play_button_color', '#ffffff');
$audio_featured_enabled = getSetting('audio_featured_enabled', '1');
$audio_newsletter_enabled = getSetting('audio_newsletter_enabled', '1');
$audio_newsletter_title = getSetting('audio_newsletter_title', 'Never Miss a Message');
$audio_newsletter_subtitle = getSetting('audio_newsletter_subtitle', 'Subscribe to get notified when new audio messages are published.');
$mini_player_background = getSetting('mini_player_background', '#ffffff');
$mini_player_text_color = getSetting('mini_player_text_color', '#333333');
$mini_player_button_background = getSetting('mini_player_button_background', '#4a7c59');
$mini_player_button_color = getSetting('mini_player_button_color', '#ffffff');
$audio_heading_font = getSetting('audio_heading_font', 'Playfair Display');
$audio_body_font = getSetting('audio_body_font', 'Inter');
$audio_heading_size = getSetting('audio_heading_size', '48');
$audio_body_size = getSetting('audio_body_size', '16');
$audio_card_shadow = getSetting('audio_card_shadow', '0 4px 6px rgba(0,0,0,0.05)');
$audio_card_hover_shadow = getSetting('audio_card_hover_shadow', '0 20px 40px rgba(0,0,0,0.15)');

// Get featured audio ID from settings
$featured_audio_id = getSetting('featured_audio_id', 0);

// Get all audio messages for featured selection (only published ones)
$audio_messages = [];
$stmt = $conn->prepare("SELECT id, title, created_at FROM audio_messages WHERE status = 'published' ORDER BY created_at DESC LIMIT 20");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $audio_messages[] = $row;
}

// Get current featured audio details
$current_featured = null;
if ($featured_audio_id > 0) {
    $stmt = $conn->prepare("SELECT id, title FROM audio_messages WHERE id = ? AND status = 'published'");
    $stmt->bind_param("i", $featured_audio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $current_featured = $result->fetch_assoc();
    }
}

// Get site settings for header
$site_title = getSetting('site_title', 'Painlesslyf');
$site_logo = getSetting('site_logo', 'assets/logo/painlesslyf-logo.png');
$primary_color = getSetting('primary_color', '#4a7c59');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Audio Page Settings - Admin Panel</title>
    
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
            border-radius: 16px;
            padding: 20px;
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
            font-size: 40px;
            color: #adb5bd;
            margin-bottom: 10px;
        }
        
        .image-upload-area p {
            color: #6c757d;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .image-upload-area small {
            color: #adb5bd;
            font-size: 11px;
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
            background: <?php echo $audio_background_color; ?>;
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
        
        select.form-control {
            cursor: pointer;
        }
        
        .status-option {
            flex: 1;
        }
        
        .status-option input {
            display: none;
        }
        
        .status-option label {
            display: block;
            padding: 12px;
            text-align: center;
            background: #f8f9fa;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid #e9ecef;
            font-weight: 500;
        }
        
        .status-option input:checked + label {
            background: <?php echo $primary_color; ?>;
            color: white;
            border-color: <?php echo $primary_color; ?>;
        }
        
        .status-option label:hover {
            background: #e9ecef;
        }
        
        .status-option input:checked + label i {
            color: white;
        }
        
        .status-option label i {
            font-size: 20px;
            margin-bottom: 5px;
            display: block;
            color: <?php echo $primary_color; ?>;
        }
        
        .shadow-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            margin-top: 10px;
        }
        
        .shadow-preview .preview-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--preview-shadow);
            transition: box-shadow 0.3s;
        }
        
        /* Additional styles for featured audio preview */
        .featured-preview {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 15px;
            border-radius: 12px;
            margin-top: 10px;
        }
        
        .featured-preview .preview-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .featured-preview .preview-icon {
            width: 50px;
            height: 50px;
            background: <?php echo $primary_color; ?>;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
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
                    <h1>Audio Page Settings</h1>
                    <p>Customize your audio messages page appearance and content</p>
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
            
            <form method="POST" action="" enctype="multipart/form-data" id="audioSettingsForm">
                <input type="hidden" name="update_audio_settings" value="1">
                
                <!-- Header Section -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-headphones"></i> Header Section</h2>
                        <p>Configure the top banner of your audio page</p>
                    </div>
                    <div class="form-body">
                        <div class="form-group">
                            <label for="audio_header_title">Page Title</label>
                            <input type="text" class="form-control" id="audio_header_title" name="audio_header_title" 
                                   value="<?php echo htmlspecialchars($audio_header_title); ?>"
                                   placeholder="Audio Messages">
                        </div>
                        
                        <div class="form-group">
                            <label for="audio_header_subtitle">Page Subtitle</label>
                            <textarea class="form-control" id="audio_header_subtitle" name="audio_header_subtitle" rows="2"><?php echo htmlspecialchars($audio_header_subtitle); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="audio_header_text_color">Header Text Color</label>
                            <div class="form-row">
                                <div style="flex: 1;">
                                    <input type="color" class="form-control" id="audio_header_text_color" name="audio_header_text_color" 
                                           value="<?php echo htmlspecialchars($audio_header_text_color); ?>"
                                           style="height: 50px; padding: 5px;">
                                </div>
                                <div style="flex: 1;">
                                    <div class="color-preview" style="background: <?php echo $audio_header_text_color; ?>;"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Header Background Type</label>
                            <div class="form-row">
                                <div class="status-option" style="flex: 1;">
                                    <input type="radio" id="bg_gradient" name="audio_header_background_type" value="gradient" <?php echo $audio_header_background_type == 'gradient' ? 'checked' : ''; ?>>
                                    <label for="bg_gradient">
                                        <i class="fas fa-chart-line"></i> Gradient
                                    </label>
                                </div>
                                <div class="status-option" style="flex: 1;">
                                    <input type="radio" id="bg_solid" name="audio_header_background_type" value="solid" <?php echo $audio_header_background_type == 'solid' ? 'checked' : ''; ?>>
                                    <label for="bg_solid">
                                        <i class="fas fa-palette"></i> Solid
                                    </label>
                                </div>
                                <div class="status-option" style="flex: 1;">
                                    <input type="radio" id="bg_image" name="audio_header_background_type" value="image" <?php echo $audio_header_background_type == 'image' ? 'checked' : ''; ?>>
                                    <label for="bg_image">
                                        <i class="fas fa-image"></i> Image
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div id="gradientSettings" style="display: <?php echo $audio_header_background_type == 'gradient' ? 'block' : 'none'; ?>;">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="audio_header_background_gradient_start">Gradient Start Color</label>
                                    <input type="color" class="form-control" name="audio_header_background_gradient_start" 
                                           value="<?php echo htmlspecialchars($audio_header_background_gradient_start); ?>"
                                           style="height: 45px;">
                                </div>
                                <div class="form-group">
                                    <label for="audio_header_background_gradient_end">Gradient End Color</label>
                                    <input type="color" class="form-control" name="audio_header_background_gradient_end" 
                                           value="<?php echo htmlspecialchars($audio_header_background_gradient_end); ?>"
                                           style="height: 45px;">
                                </div>
                            </div>
                        </div>
                        
                        <div id="solidSettings" style="display: <?php echo $audio_header_background_type == 'solid' ? 'block' : 'none'; ?>;">
                            <div class="form-group">
                                <label for="audio_header_background_solid">Solid Background Color</label>
                                <input type="color" class="form-control" name="audio_header_background_solid" 
                                       value="<?php echo htmlspecialchars($audio_header_background_solid); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                        
                        <div id="imageSettings" style="display: <?php echo $audio_header_background_type == 'image' ? 'block' : 'none'; ?>;">
                            <div class="form-group">
                                <label>Header Background Image</label>
                                <div class="image-upload-area" id="headerImageUpload">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload or drag and drop</p>
                                    <small>JPG, PNG, WEBP up to 5MB</small>
                                    <input type="file" id="audio_header_bg_image" name="audio_header_bg_image" accept="image/*" style="display: none;">
                                </div>
                                <?php if (!empty($audio_header_background_image)): ?>
                                <div class="current-image-preview">
                                    <img src="../<?php echo htmlspecialchars($audio_header_background_image); ?>" alt="Current header image">
                                    <div>
                                        <p style="font-size: 12px; color: #666;">Current image: <?php echo basename($audio_header_background_image); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="audio_header_background_overlay">Overlay Opacity (0-1)</label>
                                <input type="range" class="form-control" name="audio_header_background_overlay" 
                                       value="<?php echo htmlspecialchars($audio_header_background_overlay); ?>"
                                       min="0" max="1" step="0.1" style="padding: 0;">
                                <small>Controls the dark overlay on the background image</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Grid Settings -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-th-large"></i> Grid Settings</h2>
                        <p>Configure how audio messages are displayed</p>
                    </div>
                    <div class="form-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_grid_title">Grid Title</label>
                                <input type="text" class="form-control" id="audio_grid_title" name="audio_grid_title" 
                                       value="<?php echo htmlspecialchars($audio_grid_title); ?>"
                                       placeholder="All Audio Messages">
                            </div>
                            <div class="form-group">
                                <label for="audio_grid_subtitle">Grid Subtitle</label>
                                <input type="text" class="form-control" id="audio_grid_subtitle" name="audio_grid_subtitle" 
                                       value="<?php echo htmlspecialchars($audio_grid_subtitle); ?>"
                                       placeholder="Listen to messages on various topics">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="audio_per_page">Audio Messages Per Page</label>
                            <input type="number" class="form-control" id="audio_per_page" name="audio_per_page" 
                                   value="<?php echo htmlspecialchars($audio_per_page); ?>"
                                   min="3" max="24" step="3">
                            <small>Number of audio messages to show per page</small>
                        </div>
                    </div>
                </div>
                
                <!-- Featured Audio -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-star"></i> Featured Audio Message</h2>
                        <p>Select which audio message appears as featured on the audio page</p>
                    </div>
                    <div class="form-body">
                        <div class="form-group">
                            <div class="toggle-switch">
                                <input type="checkbox" id="audio_featured_enabled" name="audio_featured_enabled" value="1" <?php echo $audio_featured_enabled == '1' ? 'checked' : ''; ?>>
                                <label for="audio_featured_enabled">Enable Featured Audio Section</label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Current Featured Audio</label>
                            <?php if ($current_featured): ?>
                            <div class="featured-preview">
                                <div class="preview-item">
                                    <div class="preview-icon">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div>
                                        <p style="font-weight: 600;"><?php echo htmlspecialchars($current_featured['title']); ?></p>
                                        <p style="font-size: 12px; color: #666;">Audio ID: <?php echo $current_featured['id']; ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="featured-preview" style="background: #fff3e0;">
                                <div class="preview-item">
                                    <div class="preview-icon" style="background: #ff9800;">
                                        <i class="fas fa-info-circle"></i>
                                    </div>
                                    <div>
                                        <p>No featured audio selected</p>
                                        <p style="font-size: 12px;">The latest audio message will be featured automatically</p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($audio_messages)): ?>
                        <div class="form-group">
                            <label>Set Featured Audio</label>
                            <div class="form-row" style="margin-top: 10px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <select name="featured_audio_id" class="form-control">
                                        <option value="">Select an audio message...</option>
                                        <?php foreach ($audio_messages as $audio): ?>
                                        <option value="<?php echo $audio['id']; ?>" <?php echo ($current_featured && $current_featured['id'] == $audio['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($audio['title']); ?> (<?php echo date('M d, Y', strtotime($audio['created_at'])); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <button type="submit" class="btn btn-primary" style="width: 100%;" name="set_featured_audio" value="1">Set as Featured</button>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Newsletter Section -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-envelope"></i> Newsletter Section</h2>
                        <p>Configure the newsletter subscription section</p>
                    </div>
                    <div class="form-body">
                        <div class="form-group">
                            <div class="toggle-switch">
                                <input type="checkbox" id="audio_newsletter_enabled" name="audio_newsletter_enabled" value="1" <?php echo $audio_newsletter_enabled == '1' ? 'checked' : ''; ?>>
                                <label for="audio_newsletter_enabled">Enable Newsletter Section</label>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_newsletter_title">Newsletter Title</label>
                                <input type="text" class="form-control" name="audio_newsletter_title" 
                                       value="<?php echo htmlspecialchars($audio_newsletter_title); ?>">
                            </div>
                            <div class="form-group">
                                <label for="audio_newsletter_subtitle">Newsletter Subtitle</label>
                                <input type="text" class="form-control" name="audio_newsletter_subtitle" 
                                       value="<?php echo htmlspecialchars($audio_newsletter_subtitle); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Color Settings -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-palette"></i> Color Settings</h2>
                        <p>Customize the colors of your audio page</p>
                    </div>
                    <div class="form-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_background_color">Page Background Color</label>
                                <input type="color" class="form-control" name="audio_background_color" 
                                       value="<?php echo htmlspecialchars($audio_background_color); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="audio_card_background">Card Background Color</label>
                                <input type="color" class="form-control" name="audio_card_background" 
                                       value="<?php echo htmlspecialchars($audio_card_background); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_title_color">Content Title Color</label>
                                <input type="color" class="form-control" name="audio_title_color" 
                                       value="<?php echo htmlspecialchars($audio_title_color); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="audio_text_color">Text Color</label>
                                <input type="color" class="form-control" name="audio_text_color" 
                                       value="<?php echo htmlspecialchars($audio_text_color); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_meta_color">Meta Text Color</label>
                                <input type="color" class="form-control" name="audio_meta_color" 
                                       value="<?php echo htmlspecialchars($audio_meta_color); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="audio_category_background">Category Badge Background</label>
                                <input type="color" class="form-control" name="audio_category_background" 
                                       value="<?php echo htmlspecialchars($audio_category_background); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_category_color">Category Badge Text Color</label>
                                <input type="color" class="form-control" name="audio_category_color" 
                                       value="<?php echo htmlspecialchars($audio_category_color); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="audio_duration_background">Duration Badge Background</label>
                                <input type="color" class="form-control" name="audio_duration_background" 
                                       value="<?php echo htmlspecialchars($audio_duration_background); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="audio_duration_color">Duration Badge Text Color</label>
                            <input type="color" class="form-control" name="audio_duration_color" 
                                   value="<?php echo htmlspecialchars($audio_duration_color); ?>"
                                   style="height: 45px;">
                        </div>
                    </div>
                </div>
                
                <!-- Button Settings -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-hand-pointer"></i> Button & Play Button Settings</h2>
                        <p>Customize button colors and styles</p>
                    </div>
                    <div class="form-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_button_color">Button Background Color</label>
                                <input type="color" class="form-control" name="audio_button_color" 
                                       value="<?php echo htmlspecialchars($audio_button_color); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="audio_button_hover_color">Button Hover Color</label>
                                <input type="color" class="form-control" name="audio_button_hover_color" 
                                       value="<?php echo htmlspecialchars($audio_button_hover_color); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_play_button_background">Play Button Background</label>
                                <input type="color" class="form-control" name="audio_play_button_background" 
                                       value="<?php echo htmlspecialchars($audio_play_button_background); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="audio_play_button_hover_background">Play Button Hover Background</label>
                                <input type="color" class="form-control" name="audio_play_button_hover_background" 
                                       value="<?php echo htmlspecialchars($audio_play_button_hover_background); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="audio_play_button_color">Play Button Icon Color</label>
                            <input type="color" class="form-control" name="audio_play_button_color" 
                                   value="<?php echo htmlspecialchars($audio_play_button_color); ?>"
                                   style="height: 45px;">
                        </div>
                    </div>
                </div>
                
                <!-- Mini Player Settings -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-music"></i> Mini Player Settings</h2>
                        <p>Customize the bottom mini audio player</p>
                    </div>
                    <div class="form-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="mini_player_background">Player Background Color</label>
                                <input type="color" class="form-control" name="mini_player_background" 
                                       value="<?php echo htmlspecialchars($mini_player_background); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="mini_player_text_color">Player Text Color</label>
                                <input type="color" class="form-control" name="mini_player_text_color" 
                                       value="<?php echo htmlspecialchars($mini_player_text_color); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="mini_player_button_background">Player Button Background</label>
                                <input type="color" class="form-control" name="mini_player_button_background" 
                                       value="<?php echo htmlspecialchars($mini_player_button_background); ?>"
                                       style="height: 45px;">
                            </div>
                            <div class="form-group">
                                <label for="mini_player_button_color">Player Button Icon Color</label>
                                <input type="color" class="form-control" name="mini_player_button_color" 
                                       value="<?php echo htmlspecialchars($mini_player_button_color); ?>"
                                       style="height: 45px;">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card Shadow Settings -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-layer-group"></i> Card Shadow Settings</h2>
                        <p>Customize the shadow effects on audio cards</p>
                    </div>
                    <div class="form-body">
                        <div class="form-group">
                            <label for="audio_card_shadow">Normal Card Shadow</label>
                            <input type="text" class="form-control" name="audio_card_shadow" 
                                   value="<?php echo htmlspecialchars($audio_card_shadow); ?>"
                                   placeholder="0 4px 6px rgba(0,0,0,0.05)">
                            <small>Example: 0 4px 6px rgba(0,0,0,0.05)</small>
                            <div class="shadow-preview">
                                <div class="preview-card" style="box-shadow: <?php echo htmlspecialchars($audio_card_shadow); ?>">
                                    Preview Card Shadow
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="audio_card_hover_shadow">Card Hover Shadow</label>
                            <input type="text" class="form-control" name="audio_card_hover_shadow" 
                                   value="<?php echo htmlspecialchars($audio_card_hover_shadow); ?>"
                                   placeholder="0 20px 40px rgba(0,0,0,0.15)">
                            <small>Example: 0 20px 40px rgba(0,0,0,0.15)</small>
                            <div class="shadow-preview">
                                <div class="preview-card" style="box-shadow: <?php echo htmlspecialchars($audio_card_hover_shadow); ?>">
                                    Hover Preview
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Typography Settings -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-font"></i> Typography Settings</h2>
                        <p>Customize fonts and text sizes</p>
                    </div>
                    <div class="form-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_heading_font">Heading Font</label>
                                <select class="form-control" name="audio_heading_font">
                                    <option value="Playfair Display" <?php echo $audio_heading_font == 'Playfair Display' ? 'selected' : ''; ?>>Playfair Display</option>
                                    <option value="Poppins" <?php echo $audio_heading_font == 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                                    <option value="Montserrat" <?php echo $audio_heading_font == 'Montserrat' ? 'selected' : ''; ?>>Montserrat</option>
                                    <option value="Roboto" <?php echo $audio_heading_font == 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                    <option value="Merriweather" <?php echo $audio_heading_font == 'Merriweather' ? 'selected' : ''; ?>>Merriweather</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="audio_body_font">Body Font</label>
                                <select class="form-control" name="audio_body_font">
                                    <option value="Inter" <?php echo $audio_body_font == 'Inter' ? 'selected' : ''; ?>>Inter</option>
                                    <option value="Roboto" <?php echo $audio_body_font == 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                    <option value="Open Sans" <?php echo $audio_body_font == 'Open Sans' ? 'selected' : ''; ?>>Open Sans</option>
                                    <option value="Poppins" <?php echo $audio_body_font == 'Poppins' ? 'selected' : ''; ?>>Poppins</option>
                                    <option value="Lato" <?php echo $audio_body_font == 'Lato' ? 'selected' : ''; ?>>Lato</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="audio_heading_size">Heading Size (px)</label>
                                <input type="number" class="form-control" name="audio_heading_size" 
                                       value="<?php echo htmlspecialchars($audio_heading_size); ?>"
                                       min="24" max="72">
                                <small>Main page title size</small>
                            </div>
                            <div class="form-group">
                                <label for="audio_body_size">Body Text Size (px)</label>
                                <input type="number" class="form-control" name="audio_body_size" 
                                       value="<?php echo htmlspecialchars($audio_body_size); ?>"
                                       min="12" max="20">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Save All Settings
                    </button>
                    <a href="../audio.php" target="_blank" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> View Audio Page
                    </a>
                </div>
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
        
        // Toggle background settings
        const bgGradient = document.getElementById('bg_gradient');
        const bgSolid = document.getElementById('bg_solid');
        const bgImage = document.getElementById('bg_image');
        const gradientSettings = document.getElementById('gradientSettings');
        const solidSettings = document.getElementById('solidSettings');
        const imageSettings = document.getElementById('imageSettings');
        
        function toggleBackgroundSettings() {
            if (bgGradient && bgGradient.checked) {
                if (gradientSettings) gradientSettings.style.display = 'block';
                if (solidSettings) solidSettings.style.display = 'none';
                if (imageSettings) imageSettings.style.display = 'none';
            } else if (bgSolid && bgSolid.checked) {
                if (gradientSettings) gradientSettings.style.display = 'none';
                if (solidSettings) solidSettings.style.display = 'block';
                if (imageSettings) imageSettings.style.display = 'none';
            } else if (bgImage && bgImage.checked) {
                if (gradientSettings) gradientSettings.style.display = 'none';
                if (solidSettings) solidSettings.style.display = 'none';
                if (imageSettings) imageSettings.style.display = 'block';
            }
        }
        
        if (bgGradient) bgGradient.addEventListener('change', toggleBackgroundSettings);
        if (bgSolid) bgSolid.addEventListener('change', toggleBackgroundSettings);
        if (bgImage) bgImage.addEventListener('change', toggleBackgroundSettings);
        
        // Image upload handling
        const headerImageUpload = document.getElementById('headerImageUpload');
        const headerImageInput = document.getElementById('audio_header_bg_image');
        
        if (headerImageUpload) {
            headerImageUpload.addEventListener('click', () => headerImageInput.click());
            headerImageUpload.addEventListener('dragover', (e) => {
                e.preventDefault();
                headerImageUpload.style.borderColor = '<?php echo $primary_color; ?>';
            });
            headerImageUpload.addEventListener('dragleave', () => {
                headerImageUpload.style.borderColor = '#e9ecef';
            });
            headerImageUpload.addEventListener('drop', (e) => {
                e.preventDefault();
                headerImageUpload.style.borderColor = '#e9ecef';
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    headerImageInput.files = files;
                }
            });
        }
        
        // Form submission loading state
        const submitBtn = document.getElementById('submitBtn');
        const form = document.getElementById('audioSettingsForm');
        
        if (form) {
            form.addEventListener('submit', function() {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="loading"></span> Saving...';
                }
            });
        }
        
        // Auto-hide alerts
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
        
        // Shadow preview update
        const cardShadowInput = document.querySelector('input[name="audio_card_shadow"]');
        const cardHoverShadowInput = document.querySelector('input[name="audio_card_hover_shadow"]');
        const previewCards = document.querySelectorAll('.shadow-preview .preview-card');
        
        if (cardShadowInput && previewCards[0]) {
            cardShadowInput.addEventListener('input', function() {
                previewCards[0].style.boxShadow = this.value;
            });
        }
        
        if (cardHoverShadowInput && previewCards[1]) {
            cardHoverShadowInput.addEventListener('input', function() {
                previewCards[1].style.boxShadow = this.value;
            });
        }
    </script>
</body>
</html>
