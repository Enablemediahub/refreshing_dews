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
function uploadImage($file, $type = 'general') {
    $upload_dir = '../assets/uploads/';
    
    switch($type) {
        case 'hero':
            $target_dir = $upload_dir . 'hero/';
            break;
        case 'welcome':
            $target_dir = $upload_dir . 'welcome/';
            break;
        case 'cards':
            $target_dir = $upload_dir . 'cards/';
            break;
        case 'blog':
            $target_dir = $upload_dir . 'blog/';
            break;
        case 'audio':
            $target_dir = $upload_dir . 'audio/';
            break;
        case 'logo':
            $target_dir = '../assets/logo/';
            break;
        case 'favicon':
            $target_dir = '../assets/logo/';
            break;
        default:
            $target_dir = $upload_dir . 'images/';
    }
    
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_extensions)];
    }
    
    $max_size = 5 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => "File too large. Maximum size is 5MB."];
    }
    
    $new_filename = $type . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $relative_path = str_replace('../', '', $target_dir) . $new_filename;
        return ['success' => true, 'path' => $relative_path];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file.'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            // General Settings
            case 'update_general':
                updateSetting('site_title', $_POST['site_title'] ?? 'Painlesslyf');
                updateSetting('site_description', $_POST['site_description'] ?? '');
                updateSetting('footer_copyright', $_POST['footer_copyright'] ?? '');
                updateSetting('footer_description', $_POST['footer_description'] ?? '');
                $success_message = 'General settings updated successfully!';
                break;
            
            // Hero Settings
            case 'update_hero':
                updateSetting('hero_title', $_POST['hero_title'] ?? 'Painlesslyf');
                updateSetting('hero_subtitle', $_POST['hero_subtitle'] ?? '');
                updateSetting('hero_badge', $_POST['hero_badge'] ?? 'Welcome, friend');
                updateSetting('hero_logo_enabled', isset($_POST['hero_logo_enabled']) ? '1' : '0');
                updateSetting('hero_title_font_weight', $_POST['hero_title_font_weight'] ?? '900');
                updateSetting('hero_slideshow_effect', $_POST['hero_slideshow_effect'] ?? 'slide');
                updateSetting('hero_slideshow_interval', $_POST['hero_slideshow_interval'] ?? '5000');
                updateSetting('hero_button_text', $_POST['hero_button_text'] ?? 'Read the latest');
                updateSetting('hero_button_link', $_POST['hero_button_link'] ?? 'blog.php');
                
                for ($i = 1; $i <= 3; $i++) {
                    if (isset($_FILES["hero_slide_{$i}_image"]) && $_FILES["hero_slide_{$i}_image"]['error'] === UPLOAD_ERR_OK) {
                        $upload_result = uploadImage($_FILES["hero_slide_{$i}_image"], 'hero');
                        if ($upload_result['success']) {
                            updateSetting("hero_slide_{$i}_image", $upload_result['path']);
                        } else {
                            $error_message = $upload_result['error'];
                        }
                    }
                }
                
                if (empty($error_message)) {
                    $success_message = 'Hero settings updated successfully!';
                }
                break;
            
            // Announcements Settings
            case 'update_announcements':
                updateSetting('crawling_announcements', $_POST['crawling_announcements'] ?? '');
                updateSetting('announcement_speed', $_POST['announcement_speed'] ?? '20');
                $success_message = 'Announcements updated successfully!';
                break;
            
            // Feature Cards Content Settings
            case 'update_cards':
                updateSetting('cards_section_title', $_POST['cards_section_title'] ?? 'Explore the journey');
                updateSetting('cards_section_subtitle', $_POST['cards_section_subtitle'] ?? '');
                
                for ($i = 1; $i <= 3; $i++) {
                    updateSetting("card_{$i}_title", $_POST["card_{$i}_title"] ?? '');
                    updateSetting("card_{$i}_description", $_POST["card_{$i}_description"] ?? '');
                    updateSetting("card_{$i}_icon", $_POST["card_{$i}_icon"] ?? '');
                    updateSetting("card_{$i}_link", $_POST["card_{$i}_link"] ?? '#');
                }
                
                $success_message = 'Feature cards updated successfully!';
                break;
            
            // SHARED WALLPAPER + CARDS SECTION BACKGROUND
            case 'update_cards_background':
                updateSetting('shared_wallpaper_overlay_color', $_POST['shared_wallpaper_overlay_color'] ?? '#faf7f0');
                updateSetting('shared_wallpaper_overlay_opacity', $_POST['shared_wallpaper_overlay_opacity'] ?? '0.2');
                updateSetting('shared_wallpaper_card_opacity', $_POST['shared_wallpaper_card_opacity'] ?? '0.94');
                updateSetting('cards_icon_blue_color', $_POST['cards_icon_blue_color'] ?? '#2563eb');
                updateSetting('cards_title_color', $_POST['cards_title_color'] ?? '#2c3e2f');
                updateSetting('cards_subtitle_color', $_POST['cards_subtitle_color'] ?? '#6b6b6b');
                updateSetting('cards_card_background', $_POST['cards_card_background'] ?? '#ffffff');
                updateSetting('cards_card_hover_background', $_POST['cards_card_hover_background'] ?? '#ffffff');
                updateSetting('cards_card_text_color', $_POST['cards_card_text_color'] ?? '#6b6b6b');
                updateSetting('cards_card_title_color', $_POST['cards_card_title_color'] ?? '#2c3e2f');
                updateSetting('cards_card_icon_color', $_POST['cards_card_icon_color'] ?? '#2563eb');
                updateSetting('cards_card_border_color', $_POST['cards_card_border_color'] ?? 'rgba(0,0,0,0.08)');
                updateSetting('cards_card_hover_border_color', $_POST['cards_card_hover_border_color'] ?? 'rgba(186,166,142,0.3)');
                updateSetting('cards_enable_animated_background', isset($_POST['cards_enable_animated_background']) ? '1' : '0');
                
                // Legacy fallback — also save to cards_background_image
                if (isset($_FILES['shared_wallpaper_image']) && $_FILES['shared_wallpaper_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['shared_wallpaper_image'], 'cards');
                    if ($upload_result['success']) {
                        updateSetting('shared_wallpaper_image', $upload_result['path']);
                        updateSetting('cards_background_image', $upload_result['path']);
                    }
                }
                
                if (isset($_POST['remove_shared_wallpaper']) && $_POST['remove_shared_wallpaper'] == '1') {
                    updateSetting('shared_wallpaper_image', '');
                    updateSetting('cards_background_image', '');
                }
                
                // Keep legacy cards bg upload working if used
                if (isset($_FILES['cards_background_image']) && $_FILES['cards_background_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['cards_background_image'], 'cards');
                    if ($upload_result['success']) {
                        updateSetting('shared_wallpaper_image', $upload_result['path']);
                        updateSetting('cards_background_image', $upload_result['path']);
                    }
                }
                
                if (isset($_POST['remove_cards_image']) && $_POST['remove_cards_image'] == '1') {
                    updateSetting('shared_wallpaper_image', '');
                    updateSetting('cards_background_image', '');
                }
                
                $success_message = 'Shared wallpaper & cards section settings updated successfully!';
                break;
            
            // BLOG SECTION - Full Control
            case 'update_blog_section':
                updateSetting('blog_section_title', $_POST['blog_section_title'] ?? 'Latest from the blog');
                updateSetting('blog_section_subtitle', $_POST['blog_section_subtitle'] ?? 'Recent thoughts and experiences');
                updateSetting('blog_background_type', $_POST['blog_background_type'] ?? 'solid');
                updateSetting('blog_background_color', $_POST['blog_background_color'] ?? '#ffffff');
                updateSetting('blog_background_gradient_start', $_POST['blog_background_gradient_start'] ?? '#ffffff');
                updateSetting('blog_background_gradient_end', $_POST['blog_background_gradient_end'] ?? '#faf7f0');
                updateSetting('blog_background_overlay', $_POST['blog_background_overlay'] ?? 'rgba(0,0,0,0.3)');
                updateSetting('blog_title_color', $_POST['blog_title_color'] ?? '#ffffff');
                updateSetting('blog_subtitle_color', $_POST['blog_subtitle_color'] ?? '#6b6b6b');
                updateSetting('blog_card_background', $_POST['blog_card_background'] ?? '#ffffff');
                updateSetting('blog_card_hover_background', $_POST['blog_card_hover_background'] ?? '#ffffff');
                updateSetting('blog_card_text_color', $_POST['blog_card_text_color'] ?? '#6b6b6b');
                updateSetting('blog_card_title_color', $_POST['blog_card_title_color'] ?? '#2c3e2f');
                updateSetting('blog_card_button_color', $_POST['blog_card_button_color'] ?? '#2563eb');
                updateSetting('blog_card_button_hover_color', $_POST['blog_card_button_hover_color'] ?? '#1d4ed8');
                updateSetting('blog_card_shadow', $_POST['blog_card_shadow'] ?? '0 5px 15px rgba(0,0,0,0.05)');
                updateSetting('blog_card_hover_shadow', $_POST['blog_card_hover_shadow'] ?? '0 15px 30px rgba(0,0,0,0.1)');
                updateSetting('blog_enable_animated_background', isset($_POST['blog_enable_animated_background']) ? '1' : '0');
                
                if (isset($_FILES['blog_background_image']) && $_FILES['blog_background_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['blog_background_image'], 'blog');
                    if ($upload_result['success']) {
                        updateSetting('blog_background_image', $upload_result['path']);
                    }
                }
                
                if (isset($_POST['remove_blog_image']) && $_POST['remove_blog_image'] == '1') {
                    updateSetting('blog_background_image', '');
                }
                
                $success_message = 'Blog section settings updated successfully!';
                break;
            
            // AUDIO SECTION - Full Control
            case 'update_audio_section':
                updateSetting('audio_section_title', $_POST['audio_section_title'] ?? 'Recent audio messages');
                updateSetting('audio_section_subtitle', $_POST['audio_section_subtitle'] ?? 'Listen to the latest reflections');
                updateSetting('audio_background_type', $_POST['audio_background_type'] ?? 'solid');
                updateSetting('audio_background_color', $_POST['audio_background_color'] ?? '#faf7f0');
                updateSetting('audio_background_gradient_start', $_POST['audio_background_gradient_start'] ?? '#faf7f0');
                updateSetting('audio_background_gradient_end', $_POST['audio_background_gradient_end'] ?? '#f5ede0');
                updateSetting('audio_background_overlay', $_POST['audio_background_overlay'] ?? 'rgba(0,0,0,0.3)');
                updateSetting('audio_title_color', $_POST['audio_title_color'] ?? '#2c3e2f');
                updateSetting('audio_subtitle_color', $_POST['audio_subtitle_color'] ?? '#6b6b6b');
                updateSetting('audio_card_background', $_POST['audio_card_background'] ?? '#ffffff');
                updateSetting('audio_card_hover_background', $_POST['audio_card_hover_background'] ?? '#ffffff');
                updateSetting('audio_card_text_color', $_POST['audio_card_text_color'] ?? '#6b6b6b');
                updateSetting('audio_card_title_color', $_POST['audio_card_title_color'] ?? '#2c3e2f');
                updateSetting('audio_card_button_color', $_POST['audio_card_button_color'] ?? '#2563eb');
                updateSetting('audio_card_button_hover_color', $_POST['audio_card_button_hover_color'] ?? '#1d4ed8');
                updateSetting('audio_card_shadow', $_POST['audio_card_shadow'] ?? '0 5px 15px rgba(0,0,0,0.05)');
                updateSetting('audio_card_hover_shadow', $_POST['audio_card_hover_shadow'] ?? '0 15px 30px rgba(0,0,0,0.1)');
                updateSetting('audio_enable_animated_background', isset($_POST['audio_enable_animated_background']) ? '1' : '0');
                
                if (isset($_FILES['audio_background_image']) && $_FILES['audio_background_image']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['audio_background_image'], 'audio');
                    if ($upload_result['success']) {
                        updateSetting('audio_background_image', $upload_result['path']);
                    }
                }
                
                if (isset($_POST['remove_audio_image']) && $_POST['remove_audio_image'] == '1') {
                    updateSetting('audio_background_image', '');
                }
                
                $success_message = 'Audio section settings updated successfully!';
                break;
            
            // Footer Settings
            case 'update_footer':
                updateSetting('footer_gradient_start', $_POST['footer_gradient_start'] ?? '#4a7c59');
                updateSetting('footer_gradient_end', $_POST['footer_gradient_end'] ?? '#2c4a3b');
                updateSetting('footer_text_color', $_POST['footer_text_color'] ?? '#ffffff');
                updateSetting('footer_link_color', $_POST['footer_link_color'] ?? 'rgba(255,255,255,0.85)');
                updateSetting('footer_link_hover_color', $_POST['footer_link_hover_color'] ?? '#ffffff');
                updateSetting('footer_heading_color', $_POST['footer_heading_color'] ?? '#ffffff');
                updateSetting('footer_border_color', $_POST['footer_border_color'] ?? 'rgba(255,255,255,0.2)');
                $success_message = 'Footer settings updated successfully!';
                break;
            
            // Social Media Settings
            case 'update_social':
                updateSetting('facebook_url', $_POST['facebook_url'] ?? '');
                updateSetting('instagram_url', $_POST['instagram_url'] ?? '');
                updateSetting('twitter_url', $_POST['twitter_url'] ?? '');
                updateSetting('pinterest_url', $_POST['pinterest_url'] ?? '');
                updateSetting('youtube_url', $_POST['youtube_url'] ?? '');
                updateSetting('spotify_url', $_POST['spotify_url'] ?? '');
                $success_message = 'Social media links updated successfully!';
                break;
            
            // Logo & Favicon Settings
            case 'update_logo':
                if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['site_logo'], 'logo');
                    if ($upload_result['success']) {
                        updateSetting('site_logo', $upload_result['path']);
                        $success_message = 'Site logo updated successfully!';
                    }
                }
                
                if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                    $upload_result = uploadImage($_FILES['favicon'], 'favicon');
                    if ($upload_result['success']) {
                        updateSetting('favicon', $upload_result['path']);
                        $success_message = $success_message ? $success_message . ' Favicon updated.' : 'Favicon updated successfully!';
                    }
                }
                break;
            
            // Appearance Settings
            case 'update_appearance':
                updateSetting('primary_color', $_POST['primary_color'] ?? '#2563eb');
                updateSetting('theme_blue_color', $_POST['theme_blue_color'] ?? '#2563eb');
                updateSetting('theme_blue_dark_color', $_POST['theme_blue_dark_color'] ?? '#1d4ed8');
                updateSetting('theme_green_color', $_POST['theme_green_color'] ?? '#4a7c59');
                updateSetting('theme_green_dark_color', $_POST['theme_green_dark_color'] ?? '#2c4a3b');
                updateSetting('navbar_background', $_POST['navbar_background'] ?? 'rgba(255,255,255,0.95)');
                updateSetting('navbar_text_color', $_POST['navbar_text_color'] ?? '#333333');
                updateSetting('custom_css', $_POST['custom_css'] ?? '');
                updateSetting('custom_js', $_POST['custom_js'] ?? '');
                $success_message = 'Appearance settings updated successfully!';
                break;

            // Shared Button Settings
            case 'update_shared_buttons':
                updateSetting('shared_button_color', $_POST['shared_button_color'] ?? '#2563eb');
                updateSetting('shared_button_hover_color', $_POST['shared_button_hover_color'] ?? '#1d4ed8');
                updateSetting('shared_button_text_color', $_POST['shared_button_text_color'] ?? '#ffffff');
                $success_message = 'Shared button colors updated successfully!';
                break;
            
            // Reset All Settings
            case 'reset_settings':
                $default_settings = [
                    'site_title' => 'Painlesslyf',
                    'site_description' => 'Truth, grace, and the roadmap back to God\'s heart for your life and your marriage.',
                    'hero_title' => 'Painlesslyf',
                    'hero_subtitle' => 'Turn your tortuous path into a walking melody.',
                    'hero_badge' => 'Welcome, friend',
                    'hero_logo_enabled' => '1',
                    'hero_title_font_weight' => '900',
                    'hero_slideshow_effect' => 'slide',
                    'hero_slideshow_interval' => '5000',
                    'hero_button_text' => 'Read the latest',
                    'hero_button_link' => 'blog.php',
                    'cards_section_title' => 'Faith · Grace · Purpose · Melody',
                    'cards_section_subtitle' => 'A painless life is not far fetched—it is practical when we allow God to navigate.',
                    'cards_background_type' => 'gradient',
                    'cards_background_color' => '#faf7f0',
                    'cards_background_gradient_start' => '#faf7f0',
                    'cards_background_gradient_mid' => '#f5ede0',
                    'cards_background_gradient_end' => '#efe3d0',
                    'cards_title_color' => '#2c3e2f',
                    'cards_subtitle_color' => '#6b6b6b',
                    'cards_card_background' => '#ffffff',
                    'cards_card_hover_background' => '#ffffff',
                    'cards_card_text_color' => '#6b6b6b',
                    'cards_card_title_color' => '#1a2744',
                    'cards_card_icon_color' => '#C9A962',
                    'shared_wallpaper_overlay_color' => '#faf7f0',
                    'shared_wallpaper_overlay_opacity' => '0.2',
                    'shared_wallpaper_card_opacity' => '0.94',
                    'cards_icon_blue_color' => '#1a2744',
                    'blog_section_title' => 'Latest from the blog',
                    'blog_section_subtitle' => 'Truth, grace, and the roadmap for your journey',
                    'blog_background_type' => 'solid',
                    'blog_background_color' => '#ffffff',
                    'blog_background_gradient_start' => '#ffffff',
                    'blog_background_gradient_end' => '#faf7f0',
                    'blog_background_overlay' => 'rgba(0,0,0,0.3)',
                    'blog_title_color' => '#ffffff',
                    'blog_subtitle_color' => 'rgba(255,255,255,0.88)',
                    'blog_card_background' => '#ffffff',
                    'blog_card_hover_background' => '#ffffff',
                    'blog_card_text_color' => '#6b6b6b',
                    'blog_card_title_color' => '#1a2744',
                    'blog_card_button_color' => '#C9A962',
                    'blog_card_button_hover_color' => '#A68844',
                    'blog_card_shadow' => '0 5px 15px rgba(0,0,0,0.05)',
                    'blog_card_hover_shadow' => '0 15px 30px rgba(0,0,0,0.1)',
                    'blog_enable_animated_background' => '0',
                    'audio_section_title' => 'Recent audio messages',
                    'audio_section_subtitle' => 'Listen to the latest reflections',
                    'audio_background_type' => 'solid',
                    'audio_background_color' => '#faf7f0',
                    'audio_background_gradient_start' => '#faf7f0',
                    'audio_background_gradient_end' => '#f5ede0',
                    'audio_background_overlay' => 'rgba(0,0,0,0.3)',
                    'audio_title_color' => '#2c3e2f',
                    'audio_subtitle_color' => '#6b6b6b',
                    'audio_card_background' => '#ffffff',
                    'audio_card_hover_background' => '#ffffff',
                    'audio_card_text_color' => '#6b6b6b',
                    'audio_card_title_color' => '#2c3e2f',
                    'audio_card_button_color' => '#2563eb',
                    'audio_card_button_hover_color' => '#1d4ed8',
                    'audio_card_shadow' => '0 5px 15px rgba(0,0,0,0.05)',
                    'audio_card_hover_shadow' => '0 15px 30px rgba(0,0,0,0.1)',
                    'audio_enable_animated_background' => '0',
                    'shared_button_color' => '#2563eb',
                    'shared_button_hover_color' => '#1d4ed8',
                    'shared_button_text_color' => '#ffffff',
                    'footer_gradient_start' => '#4a7c59',
                    'footer_gradient_end' => '#2c4a3b',
                    'footer_text_color' => '#ffffff',
                    'footer_link_color' => 'rgba(255,255,255,0.7)',
                    'footer_link_hover_color' => '#C9A962',
                    'footer_heading_color' => '#ffffff',
                    'footer_border_color' => 'rgba(201, 169, 98, 0.2)',
                    'footer_copyright' => '© 2026 Painlesslyf. All rights reserved.',
                    'footer_description' => 'A space for the brave. No sugarcoating. No fluff. Just truth, grace, and the roadmap back to God\'s heart for your life and your marriage.',
                    'primary_color' => '#C9A962',
                    'theme_blue_color' => '#1a2744',
                    'theme_blue_dark_color' => '#0f1824',
                    'theme_green_color' => '#C9A962',
                    'theme_green_dark_color' => '#A68844',
                    'navbar_background' => 'rgba(255,255,255,0.95)',
                    'navbar_text_color' => '#333333',
                    'card_1_title' => 'Truth & Grace',
                    'card_1_description' => 'Pure, unvarnished truth on marriage, faith, and the journey back to God\'s design.',
                    'card_1_icon' => '📝',
                    'card_1_link' => 'blog.php',
                    'card_2_title' => 'Audio Messages',
                    'card_2_description' => 'Listen to messages of truth and grace—words to illuminate, not to shame.',
                    'card_2_icon' => '🎧',
                    'card_2_link' => 'audio.php',
                    'card_3_title' => 'About Me',
                    'card_3_description' => 'The vision behind Painlesslyf—a space for the brave seeking practical peace.',
                    'card_3_icon' => '🤝',
                    'card_3_link' => 'about.php',
                    'crawling_announcements' => 'Welcome to Painlesslyf | Faith · Grace · Purpose · Melody | Truth for the brave',
                    'announcement_speed' => '20'
                ];
                
                foreach ($default_settings as $key => $value) {
                    updateSetting($key, $value);
                }
                
                $success_message = 'All settings reset to default values!';
                break;
        }
        
        // Refresh settings after updates
        $result = $conn->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Complete Site Settings - Admin Panel</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
        
        .admin-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2c3e50 0%, #1e2b37 100%);
            color: white;
            height: 100vh;
            overflow-y: auto;
            position: sticky;
            top: 0;
            flex-shrink: 0;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header img {
            max-width: 120px;
            margin-bottom: 15px;
            background: white;
            padding: 10px;
            border-radius: 10px;
        }
        
        .sidebar-header h3 {
            font-size: 18px;
            font-weight: 600;
        }
        
        .sidebar-header p {
            font-size: 13px;
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
        }
        
        .sidebar-menu-item:hover,
        .sidebar-menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #2563eb;
        }
        
        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 15px 20px;
        }
        
        .sidebar-menu-label {
            padding: 10px 25px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
        }
        
        .admin-main {
            flex: 1;
            padding: 30px;
            overflow-x: hidden;
            min-width: 0;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1500;
            background: #2563eb;
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

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1500;
        }

        .sidebar-overlay.active {
            display: block;
        }
        
        .top-nav {
            background: white;
            padding: 20px 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
            background: linear-gradient(135deg, #2563eb, #4a7c59);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .settings-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            background: white;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: #f8f9fa;
            color: #666;
            border-radius: 10px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .tab-btn:hover {
            background: #e9ecef;
            color: #333;
        }
        
        .tab-btn.active {
            background: #2563eb;
            color: white;
        }
        
        .settings-panel {
            display: none;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .settings-panel.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
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
            margin-right: 6px;
            color: #2563eb;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(186,166,142,0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        select.form-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
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
            border-color: #2563eb;
            background: #f1f8f1;
        }
        
        .file-upload-label i {
            color: #2563eb;
        }
        
        .preview-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
        }
        
        .color-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .color-input-group input[type="color"] {
            width: 50px;
            height: 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
        }
        
        .color-input-group .form-control {
            flex: 1;
        }
        
        .range-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .range-group input[type="range"] {
            flex: 1;
        }
        
        .bg-options {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .bg-options h4 {
            color: #2563eb;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
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
        
        .preview-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .welcome-preview {
            background: linear-gradient(135deg, #2563eb, #4a7c59);
            padding: 40px 20px;
            text-align: center;
            border-radius: 10px;
            color: white;
        }
        
        .welcome-preview h3 {
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .welcome-preview .divider {
            width: 60px;
            height: 3px;
            background: #f5e6d3;
            margin: 0 auto 20px;
        }
        
        .welcome-preview p {
            max-width: 600px;
            margin: 0 auto;
            font-size: 14px;
            line-height: 1.6;
        }
        
        @media (max-width: 1024px) {
            .admin-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                transform: translateX(-100%);
                z-index: 2000;
                transition: transform 0.3s ease;
            }

            .admin-sidebar.sidebar-open {
                transform: translateX(0);
            }

            .mobile-menu-toggle {
                display: flex;
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
                padding: 20px;
            }
            
            .settings-tabs {
                flex-direction: column;
            }
            
            .tab-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        .mt-2 { margin-top: 10px; }
        .mb-2 { margin-bottom: 10px; }
        .text-center { text-align: center; }
        .w-100 { width: 100%; }
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
                    <h1>Site Settings</h1>
                    <p>Complete control over your website's appearance and content</p>
                </div>
                <div class="top-nav-user">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($admin_username); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                    <div class="user-avatar"><i class="fas fa-user"></i></div>
                </div>
            </div>
            
            <div class="settings-container">
                <?php if ($success_message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <div class="settings-tabs">
                    <button class="tab-btn active" onclick="showTab('general')"><i class="fas fa-globe"></i> General</button>
                    <button class="tab-btn" onclick="showTab('hero')"><i class="fas fa-images"></i> Hero</button>
                    <button class="tab-btn" onclick="showTab('announcements')"><i class="fas fa-bullhorn"></i> Announcements</button>
                    <button class="tab-btn" onclick="showTab('cards')"><i class="fas fa-th"></i> Feature Cards</button>
                    <button class="tab-btn" onclick="showTab('cards-bg')"><i class="fas fa-palette"></i> Shared Wallpaper</button>
                    <button class="tab-btn" onclick="showTab('blog-bg')"><i class="fas fa-blog"></i> Blog Section</button>
                    <button class="tab-btn" onclick="showTab('audio-bg')"><i class="fas fa-headphones"></i> Audio Section</button>
                    <button class="tab-btn" onclick="showTab('footer')"><i class="fas fa-football-ball"></i> Footer</button>
                    <button class="tab-btn" onclick="showTab('social')"><i class="fas fa-share-alt"></i> Social</button>
                    <button class="tab-btn" onclick="showTab('logo')"><i class="fas fa-paint-brush"></i> Logo</button>
                    <button class="tab-btn" onclick="showTab('appearance')"><i class="fas fa-palette"></i> Appearance</button>
                    <button class="tab-btn" onclick="showTab('shared-buttons')"><i class="fas fa-hand-pointer"></i> Shared Buttons</button>
                </div>
                
                <!-- General Settings Panel -->
                <div id="general-panel" class="settings-panel active">
                    <div class="panel-header">
                        <h2>General Settings</h2>
                        <p>Basic website information</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_general">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-tag"></i> Site Title</label>
                                <input type="text" name="site_title" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('site_title', 'Painlesslyf')); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label><i class="fas fa-align-left"></i> Site Description</label>
                                <textarea name="site_description" class="form-control" rows="3"><?php echo htmlspecialchars(getSettingValue('site_description', '')); ?></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label><i class="fas fa-copyright"></i> Footer Copyright</label>
                                <input type="text" name="footer_copyright" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('footer_copyright', '© 2025 Painlesslyf. All rights reserved.')); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label><i class="fas fa-info-circle"></i> Footer Description</label>
                                <textarea name="footer_description" class="form-control" rows="2"><?php echo htmlspecialchars(getSettingValue('footer_description', 'Sharing daily life experiences and audio messages of hope, inspiration, and personal growth.')); ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button>
                        </div>
                    </form>
                </div>
                
                <!-- Hero Settings Panel -->
                <div id="hero-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Hero Section</h2>
                        <p>Configure the main hero banner. Use 1920×1080 images for full-width display. The site logo appears as a circle on the hero.</p>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_hero">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-certificate"></i> Hero Badge Text</label>
                                <input type="text" name="hero_badge" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('hero_badge', 'Welcome, friend')); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label><i class="fas fa-heading"></i> Hero Title (e.g. Painlesslyf)</label>
                                <input type="text" name="hero_title" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('hero_title', 'Painlesslyf')); ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-bold"></i> Title Font Weight</label>
                                <select name="hero_title_font_weight" class="form-control">
                                    <?php foreach (['700' => 'Bold (700)', '800' => 'Extra Bold (800)', '900' => 'Black (900)'] as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo getSettingValue('hero_title_font_weight', '900') == $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small>Makes the hero title display more prominently</small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-film"></i> Slideshow Transition</label>
                                <select name="hero_slideshow_effect" class="form-control">
                                    <option value="slide" <?php echo getSettingValue('hero_slideshow_effect', 'slide') == 'slide' ? 'selected' : ''; ?>>Slide left to right</option>
                                    <option value="fade" <?php echo getSettingValue('hero_slideshow_effect') == 'fade' ? 'selected' : ''; ?>>Fade</option>
                                </select>
                            </div>
                            <div class="form-group full-width">
                                <label><i class="fas fa-subtitle"></i> Hero Subtitle</label>
                                <input type="text" name="hero_subtitle" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('hero_subtitle', 'Daily inspiration. Honest reflections. Messages to refresh your spirit.')); ?>">
                            </div>
                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <input type="checkbox" name="hero_logo_enabled" id="hero_logo_enabled" <?php echo getSettingValue('hero_logo_enabled', '1') == '1' ? 'checked' : ''; ?>>
                                    <label for="hero_logo_enabled">Show circular logo on hero (uses site logo)</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Slideshow Interval (ms)</label>
                                <input type="number" name="hero_slideshow_interval" class="form-control" value="<?php echo getSettingValue('hero_slideshow_interval', '5000'); ?>">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-link"></i> Button Link (Read the latest)</label>
                                <input type="text" name="hero_button_link" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('hero_button_link', 'blog.php')); ?>">
                            </div>
                            
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="form-group full-width" style="background: #f8f9fa; padding: 15px; border-radius: 10px;">
                                <h3 style="margin-bottom: 15px; color: #2563eb;">Slide <?php echo $i; ?></h3>
                                <div class="file-upload">
                                    <input type="file" name="hero_slide_<?php echo $i; ?>_image" id="slide_<?php echo $i; ?>" accept="image/*">
                                    <label for="slide_<?php echo $i; ?>" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i> Choose Image
                                    </label>
                                    <?php $slide_img = getSettingValue("hero_slide_{$i}_image"); ?>
                                    <?php if (!empty($slide_img) && file_exists("../" . $slide_img)): ?>
                                    <img src="../<?php echo $slide_img; ?>" class="preview-image" alt="Preview">
                                    <?php endif; ?>
                                </div>
                                <div class="file-info mt-2">Recommended: 1920x1080px</div>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Hero Settings</button>
                        </div>
                    </form>
                </div>
                <!-- Announcements Panel -->
                <div id="announcements-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Announcements Ticker</h2>
                        <p>Scrolling announcements at the top of the page</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_announcements">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-list"></i> Announcements (separate with | )</label>
                                <textarea name="crawling_announcements" class="form-control" rows="4"><?php echo htmlspecialchars(getSettingValue('crawling_announcements', 'Welcome, friend | New audio message every week | Latest blog post: Finding Peace in Chaos')); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-tachometer-alt"></i> Scroll Speed (seconds)</label>
                                <input type="number" name="announcement_speed" class="form-control" min="5" max="60" value="<?php echo getSettingValue('announcement_speed', '20'); ?>">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Announcements</button>
                        </div>
                    </form>
                </div>
                
                <!-- Feature Cards Content Panel -->
                <div id="cards-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Feature Cards Content</h2>
                        <p>Edit the three main feature cards</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_cards">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-heading"></i> Section Title</label>
                                <input type="text" name="cards_section_title" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('cards_section_title', 'Explore the journey')); ?>">
                            </div>
                            <div class="form-group full-width">
                                <label><i class="fas fa-align-left"></i> Section Subtitle</label>
                                <input type="text" name="cards_section_subtitle" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('cards_section_subtitle', 'Thanks to words and audio, we are able to share experiences and bridge distances.')); ?>">
                            </div>
                            
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div style="grid-column: span 2; background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 10px;">
                                <h3 style="color: #2563eb; margin-bottom: 15px;">Card <?php echo $i; ?></h3>
                                <div class="form-grid" style="grid-template-columns: repeat(2, 1fr);">
                                    <div class="form-group">
                                        <label>Title</label>
                                        <input type="text" name="card_<?php echo $i; ?>_title" class="form-control" value="<?php echo htmlspecialchars(getSettingValue("card_{$i}_title", '')); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Icon (Emoji)</label>
                                        <input type="text" name="card_<?php echo $i; ?>_icon" class="form-control" value="<?php echo htmlspecialchars(getSettingValue("card_{$i}_icon", '📝')); ?>">
                                    </div>
                                    <div class="form-group full-width" style="grid-column: span 2;">
                                        <label>Description</label>
                                        <textarea name="card_<?php echo $i; ?>_description" class="form-control" rows="2"><?php echo htmlspecialchars(getSettingValue("card_{$i}_description", '')); ?></textarea>
                                    </div>
                                    <div class="form-group full-width" style="grid-column: span 2;">
                                        <label>Link</label>
                                        <input type="text" name="card_<?php echo $i; ?>_link" class="form-control" value="<?php echo htmlspecialchars(getSettingValue("card_{$i}_link", '#')); ?>">
                                    </div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Cards</button>
                        </div>
                    </form>
                </div>
                
                <!-- Shared Wallpaper Panel (Explore + Blog sections) -->
                <div id="cards-bg-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Shared Wallpaper</h2>
                        <p>Controls the fixed background behind both "Explore the journey" and "Latest from the blog". Adjust overlay if text is hard to read.</p>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_cards_background">
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-image"></i> Wallpaper Image</label>
                                <div class="file-upload">
                                    <input type="file" name="shared_wallpaper_image" id="shared_wallpaper_img" accept="image/*">
                                    <label for="shared_wallpaper_img" class="file-upload-label"><i class="fas fa-cloud-upload-alt"></i> Upload Wallpaper</label>
                                    <?php
                                    $wallpaper_img = getSettingValue('shared_wallpaper_image');
                                    if (empty($wallpaper_img)) {
                                        $wallpaper_img = getSettingValue('cards_background_image');
                                    }
                                    ?>
                                    <?php if (!empty($wallpaper_img)): ?>
                                    <img src="../<?php echo $wallpaper_img; ?>" class="preview-image" alt="Wallpaper preview">
                                    <button type="button" class="btn btn-danger" style="padding: 5px 10px;" onclick="document.getElementById('remove_shared_wallpaper').value='1'; this.form.submit();">Remove Wallpaper</button>
                                    <input type="hidden" name="remove_shared_wallpaper" id="remove_shared_wallpaper" value="0">
                                    <?php endif; ?>
                                </div>
                                <small>Recommended: wide landscape image. Leave empty to use the default pattern.</small>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-layer-group"></i> Overlay Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="shared_wallpaper_overlay_color" value="<?php echo getSettingValue('shared_wallpaper_overlay_color', '#faf7f0'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('shared_wallpaper_overlay_color', '#faf7f0'); ?>" readonly>
                                </div>
                                <small>Color tint over the wallpaper</small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-adjust"></i> Overlay Opacity</label>
                                <div class="range-group">
                                    <input type="range" name="shared_wallpaper_overlay_opacity" min="0" max="1" step="0.05" value="<?php echo getSettingValue('shared_wallpaper_overlay_opacity', '0.2'); ?>" oninput="this.nextElementSibling.textContent=this.value">
                                    <span><?php echo getSettingValue('shared_wallpaper_overlay_opacity', '0.2'); ?></span>
                                </div>
                                <small>Lower = wallpaper shows through more. Raise if text is hard to read.</small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-square"></i> Card Background Opacity</label>
                                <div class="range-group">
                                    <input type="range" name="shared_wallpaper_card_opacity" min="0.5" max="1" step="0.02" value="<?php echo getSettingValue('shared_wallpaper_card_opacity', '0.94'); ?>" oninput="this.nextElementSibling.textContent=this.value">
                                    <span><?php echo getSettingValue('shared_wallpaper_card_opacity', '0.94'); ?></span>
                                </div>
                                <small>Opacity of the white card boxes over the wallpaper</small>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-icons"></i> Card Icon Blue Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="cards_icon_blue_color" value="<?php echo getSettingValue('cards_icon_blue_color', '#2563eb'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('cards_icon_blue_color', '#2563eb'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <h4><i class="fas fa-font"></i> Explore Section Text Colors</h4>
                        </div>
                        <div class="form-group">
                            <label>Section Title Color</label>
                            <div class="color-input-group">
                                <input type="color" name="cards_title_color" value="<?php echo getSettingValue('cards_title_color', '#2c3e2f'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('cards_title_color', '#2c3e2f'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Section Subtitle Color</label>
                            <div class="color-input-group">
                                <input type="color" name="cards_subtitle_color" value="<?php echo getSettingValue('cards_subtitle_color', '#6b6b6b'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('cards_subtitle_color', '#6b6b6b'); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <h4><i class="fas fa-id-card"></i> Card Styling</h4>
                        </div>
                        <div class="form-group">
                            <label>Card Background</label>
                            <div class="color-input-group">
                                <input type="color" name="cards_card_background" value="<?php echo getSettingValue('cards_card_background', '#ffffff'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('cards_card_background', '#ffffff'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Hover Background</label>
                            <div class="color-input-group">
                                <input type="color" name="cards_card_hover_background" value="<?php echo getSettingValue('cards_card_hover_background', '#ffffff'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('cards_card_hover_background', '#ffffff'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Title Color</label>
                            <div class="color-input-group">
                                <input type="color" name="cards_card_title_color" value="<?php echo getSettingValue('cards_card_title_color', '#2c3e2f'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('cards_card_title_color', '#2c3e2f'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Text Color</label>
                            <div class="color-input-group">
                                <input type="color" name="cards_card_text_color" value="<?php echo getSettingValue('cards_card_text_color', '#6b6b6b'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('cards_card_text_color', '#6b6b6b'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Icon Color (legacy)</label>
                            <div class="color-input-group">
                                <input type="color" name="cards_card_icon_color" value="<?php echo getSettingValue('cards_card_icon_color', '#2563eb'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('cards_card_icon_color', '#2563eb'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <div class="checkbox-group">
                                <input type="checkbox" name="cards_enable_animated_background" id="cards_animated" <?php echo getSettingValue('cards_enable_animated_background', '0') == '1' ? 'checked' : ''; ?>>
                                <label for="cards_animated">Enable Animated Background Bubbles</label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Shared Wallpaper</button>
                        </div>
                    </form>
                </div>
                
                <!-- BLOG SECTION - Full Control Panel -->
                <div id="blog-bg-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Blog Section Settings</h2>
                        <p>Customize the "Latest from the blog" section titles and card styling. The background wallpaper is controlled in the <strong>Shared Wallpaper</strong> tab.</p>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_blog_section">
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-heading"></i> Section Title</label>
                                <input type="text" name="blog_section_title" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_section_title', 'Latest from the blog')); ?>">
                                <small>This is the main heading for the blog section</small>
                            </div>
                            <div class="form-group full-width">
                                <label><i class="fas fa-align-left"></i> Section Subtitle</label>
                                <input type="text" name="blog_section_subtitle" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('blog_section_subtitle', 'Recent thoughts and experiences')); ?>">
                                <small>This appears below the main heading</small>
                            </div>
                            
                            <div class="form-group full-width">
                                <label><i class="fas fa-fill-drip"></i> Background Type</label>
                                <select name="blog_background_type" id="blog_bg_type" class="form-control" onchange="toggleBlogBgOptions()">
                                    <option value="solid" <?php echo getSettingValue('blog_background_type', 'solid') == 'solid' ? 'selected' : ''; ?>>Solid Color</option>
                                    <option value="gradient" <?php echo getSettingValue('blog_background_type') == 'gradient' ? 'selected' : ''; ?>>Gradient</option>
                                    <option value="image" <?php echo getSettingValue('blog_background_type') == 'image' ? 'selected' : ''; ?>>Background Image</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="blog_solid_options" class="bg-options" style="display: <?php echo getSettingValue('blog_background_type', 'solid') == 'solid' ? 'block' : 'none'; ?>;">
                            <h4><i class="fas fa-palette"></i> Solid Color Settings</h4>
                            <div class="form-group">
                                <label>Background Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_background_color" value="<?php echo getSettingValue('blog_background_color', '#ffffff'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('blog_background_color', '#ffffff'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div id="blog_gradient_options" class="bg-options" style="display: <?php echo getSettingValue('blog_background_type') == 'gradient' ? 'block' : 'none'; ?>;">
                            <h4><i class="fas fa-chrome"></i> Gradient Settings</h4>
                            <div class="form-group">
                                <label>Gradient Start Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_background_gradient_start" value="<?php echo getSettingValue('blog_background_gradient_start', '#ffffff'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('blog_background_gradient_start', '#ffffff'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Gradient End Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="blog_background_gradient_end" value="<?php echo getSettingValue('blog_background_gradient_end', '#faf7f0'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('blog_background_gradient_end', '#faf7f0'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div id="blog_image_options" class="bg-options" style="display: <?php echo getSettingValue('blog_background_type') == 'image' ? 'block' : 'none'; ?>;">
                            <h4><i class="fas fa-image"></i> Background Image Settings</h4>
                            <div class="form-group">
                                <label>Background Image</label>
                                <div class="file-upload">
                                    <input type="file" name="blog_background_image" id="blog_bg_img" accept="image/*">
                                    <label for="blog_bg_img" class="file-upload-label"><i class="fas fa-cloud-upload-alt"></i> Choose Image</label>
                                    <?php $blog_img = getSettingValue('blog_background_image'); ?>
                                    <?php if (!empty($blog_img)): ?>
                                    <img src="../<?php echo $blog_img; ?>" class="preview-image" alt="Preview">
                                    <button type="button" class="btn btn-danger" style="padding: 5px 10px;" onclick="document.getElementById('remove_blog_image').value='1'; this.form.submit();">Remove</button>
                                    <input type="hidden" name="remove_blog_image" id="remove_blog_image" value="0">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Overlay Opacity</label>
                                <div class="range-group">
                                    <input type="range" name="blog_background_overlay" min="0" max="1" step="0.05" value="<?php echo getSettingValue('blog_background_overlay', '0.3'); ?>">
                                    <span><?php echo getSettingValue('blog_background_overlay', '0.3'); ?></span>
                                </div>
                                <small>Darkens the image for better text readability</small>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <h4><i class="fas fa-font"></i> Text Colors</h4>
                        </div>
                        <div class="form-group">
                            <label>Section Title Color</label>
                            <div class="color-input-group">
                                <input type="color" name="blog_title_color" value="<?php echo getSettingValue('blog_title_color', '#ffffff'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('blog_title_color', '#ffffff'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Section Subtitle Color</label>
                            <div class="color-input-group">
                                <input type="color" name="blog_subtitle_color" value="<?php echo getSettingValue('blog_subtitle_color', '#6b6b6b'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('blog_subtitle_color', '#6b6b6b'); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <h4><i class="fas fa-id-card"></i> Card Styling</h4>
                        </div>
                        <div class="form-group">
                            <label>Card Background Color</label>
                            <div class="color-input-group">
                                <input type="color" name="blog_card_background" value="<?php echo getSettingValue('blog_card_background', '#ffffff'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('blog_card_background', '#ffffff'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Hover Background</label>
                            <div class="color-input-group">
                                <input type="color" name="blog_card_hover_background" value="<?php echo getSettingValue('blog_card_hover_background', '#ffffff'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('blog_card_hover_background', '#ffffff'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Title Color</label>
                            <div class="color-input-group">
                                <input type="color" name="blog_card_title_color" value="<?php echo getSettingValue('blog_card_title_color', '#2c3e2f'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('blog_card_title_color', '#2c3e2f'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Text Color</label>
                            <div class="color-input-group">
                                <input type="color" name="blog_card_text_color" value="<?php echo getSettingValue('blog_card_text_color', '#6b6b6b'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('blog_card_text_color', '#6b6b6b'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Button Color</label>
                            <div class="color-input-group">
                                <input type="color" name="blog_card_button_color" value="<?php echo getSettingValue('blog_card_button_color', '#2563eb'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('blog_card_button_color', '#2563eb'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Button Hover Color</label>
                            <div class="color-input-group">
                                <input type="color" name="blog_card_button_hover_color" value="<?php echo getSettingValue('blog_card_button_hover_color', '#1d4ed8'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('blog_card_button_hover_color', '#1d4ed8'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <div class="checkbox-group">
                                <input type="checkbox" name="blog_enable_animated_background" id="blog_animated" <?php echo getSettingValue('blog_enable_animated_background', '0') == '1' ? 'checked' : ''; ?>>
                                <label for="blog_animated">Enable Animated Background Bubbles</label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Blog Section Settings</button>
                        </div>
                    </form>
                </div>
                
                <!-- AUDIO SECTION - Full Control Panel -->
                <div id="audio-bg-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Audio Section Settings</h2>
                        <p>Customize the "Recent audio messages" section - Title, Subtitle, Background, and Card Styling</p>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_audio_section">
                        
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-heading"></i> Section Title</label>
                                <input type="text" name="audio_section_title" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('audio_section_title', 'Recent audio messages')); ?>">
                                <small>This is the main heading for the audio section</small>
                            </div>
                            <div class="form-group full-width">
                                <label><i class="fas fa-align-left"></i> Section Subtitle</label>
                                <input type="text" name="audio_section_subtitle" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('audio_section_subtitle', 'Listen to the latest reflections')); ?>">
                                <small>This appears below the main heading</small>
                            </div>
                            
                            <div class="form-group full-width">
                                <label><i class="fas fa-fill-drip"></i> Background Type</label>
                                <select name="audio_background_type" id="audio_bg_type" class="form-control" onchange="toggleAudioBgOptions()">
                                    <option value="solid" <?php echo getSettingValue('audio_background_type', 'solid') == 'solid' ? 'selected' : ''; ?>>Solid Color</option>
                                    <option value="gradient" <?php echo getSettingValue('audio_background_type') == 'gradient' ? 'selected' : ''; ?>>Gradient</option>
                                    <option value="image" <?php echo getSettingValue('audio_background_type') == 'image' ? 'selected' : ''; ?>>Background Image</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="audio_solid_options" class="bg-options" style="display: <?php echo getSettingValue('audio_background_type', 'solid') == 'solid' ? 'block' : 'none'; ?>;">
                            <h4><i class="fas fa-palette"></i> Solid Color Settings</h4>
                            <div class="form-group">
                                <label>Background Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="audio_background_color" value="<?php echo getSettingValue('audio_background_color', '#faf7f0'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('audio_background_color', '#faf7f0'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div id="audio_gradient_options" class="bg-options" style="display: <?php echo getSettingValue('audio_background_type') == 'gradient' ? 'block' : 'none'; ?>;">
                            <h4><i class="fas fa-chrome"></i> Gradient Settings</h4>
                            <div class="form-group">
                                <label>Gradient Start Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="audio_background_gradient_start" value="<?php echo getSettingValue('audio_background_gradient_start', '#faf7f0'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('audio_background_gradient_start', '#faf7f0'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Gradient End Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="audio_background_gradient_end" value="<?php echo getSettingValue('audio_background_gradient_end', '#f5ede0'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('audio_background_gradient_end', '#f5ede0'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <div id="audio_image_options" class="bg-options" style="display: <?php echo getSettingValue('audio_background_type') == 'image' ? 'block' : 'none'; ?>;">
                            <h4><i class="fas fa-image"></i> Background Image Settings</h4>
                            <div class="form-group">
                                <label>Background Image</label>
                                <div class="file-upload">
                                    <input type="file" name="audio_background_image" id="audio_bg_img" accept="image/*">
                                    <label for="audio_bg_img" class="file-upload-label"><i class="fas fa-cloud-upload-alt"></i> Choose Image</label>
                                    <?php $audio_img = getSettingValue('audio_background_image'); ?>
                                    <?php if (!empty($audio_img)): ?>
                                    <img src="../<?php echo $audio_img; ?>" class="preview-image" alt="Preview">
                                    <button type="button" class="btn btn-danger" style="padding: 5px 10px;" onclick="document.getElementById('remove_audio_image').value='1'; this.form.submit();">Remove</button>
                                    <input type="hidden" name="remove_audio_image" id="remove_audio_image" value="0">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Overlay Opacity</label>
                                <div class="range-group">
                                    <input type="range" name="audio_background_overlay" min="0" max="1" step="0.05" value="<?php echo getSettingValue('audio_background_overlay', '0.3'); ?>">
                                    <span><?php echo getSettingValue('audio_background_overlay', '0.3'); ?></span>
                                </div>
                                <small>Darkens the image for better text readability</small>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <h4><i class="fas fa-font"></i> Text Colors</h4>
                        </div>
                        <div class="form-group">
                            <label>Section Title Color</label>
                            <div class="color-input-group">
                                <input type="color" name="audio_title_color" value="<?php echo getSettingValue('audio_title_color', '#2c3e2f'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('audio_title_color', '#2c3e2f'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Section Subtitle Color</label>
                            <div class="color-input-group">
                                <input type="color" name="audio_subtitle_color" value="<?php echo getSettingValue('audio_subtitle_color', '#6b6b6b'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('audio_subtitle_color', '#6b6b6b'); ?>" readonly>
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <h4><i class="fas fa-id-card"></i> Card Styling</h4>
                        </div>
                        <div class="form-group">
                            <label>Card Background Color</label>
                            <div class="color-input-group">
                                <input type="color" name="audio_card_background" value="<?php echo getSettingValue('audio_card_background', '#ffffff'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('audio_card_background', '#ffffff'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Hover Background</label>
                            <div class="color-input-group">
                                <input type="color" name="audio_card_hover_background" value="<?php echo getSettingValue('audio_card_hover_background', '#ffffff'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('audio_card_hover_background', '#ffffff'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Title Color</label>
                            <div class="color-input-group">
                                <input type="color" name="audio_card_title_color" value="<?php echo getSettingValue('audio_card_title_color', '#2c3e2f'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('audio_card_title_color', '#2c3e2f'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Card Text Color</label>
                            <div class="color-input-group">
                                <input type="color" name="audio_card_text_color" value="<?php echo getSettingValue('audio_card_text_color', '#6b6b6b'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('audio_card_text_color', '#6b6b6b'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Button Color</label>
                            <div class="color-input-group">
                                <input type="color" name="audio_card_button_color" value="<?php echo getSettingValue('audio_card_button_color', '#2563eb'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('audio_card_button_color', '#2563eb'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Button Hover Color</label>
                            <div class="color-input-group">
                                <input type="color" name="audio_card_button_hover_color" value="<?php echo getSettingValue('audio_card_button_hover_color', '#1d4ed8'); ?>">
                                <input type="text" class="form-control" value="<?php echo getSettingValue('audio_card_button_hover_color', '#1d4ed8'); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <div class="checkbox-group">
                                <input type="checkbox" name="audio_enable_animated_background" id="audio_animated" <?php echo getSettingValue('audio_enable_animated_background', '0') == '1' ? 'checked' : ''; ?>>
                                <label for="audio_animated">Enable Animated Background Bubbles</label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Audio Section Settings</button>
                        </div>
                    </form>
                </div>
                
                <!-- Footer Settings Panel -->
                <div id="footer-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Footer Settings</h2>
                        <p>Blue dew gradient footer — consistent across all pages</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_footer">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Gradient Start (Forest Green)</label>
                                <div class="color-input-group">
                                    <input type="color" name="footer_gradient_start" value="<?php echo getSettingValue('footer_gradient_start', '#4a7c59'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('footer_gradient_start', '#4a7c59'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Gradient End (Emerald Dark)</label>
                                <div class="color-input-group">
                                    <input type="color" name="footer_gradient_end" value="<?php echo getSettingValue('footer_gradient_end', '#2c4a3b'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('footer_gradient_end', '#2c4a3b'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="footer_text_color" value="<?php echo getSettingValue('footer_text_color', '#ffffff'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('footer_text_color', '#ffffff'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Link Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="footer_link_color" value="<?php echo getSettingValue('footer_link_color', '#e0e0e0'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('footer_link_color', '#e0e0e0'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Link Hover Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="footer_link_hover_color" value="<?php echo getSettingValue('footer_link_hover_color', '#2563eb'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('footer_link_hover_color', '#2563eb'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Heading Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="footer_heading_color" value="<?php echo getSettingValue('footer_heading_color', '#ffffff'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('footer_heading_color', '#ffffff'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Border Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="footer_border_color" value="<?php echo getSettingValue('footer_border_color', '#333333'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('footer_border_color', '#333333'); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Footer Settings</button>
                        </div>
                    </form>
                </div>
                
                <!-- Social Media Panel -->
                <div id="social-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Social Media Links</h2>
                        <p>Connect your social media accounts</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_social">
                        <div class="form-grid">
                            <div class="form-group"><label><i class="fab fa-facebook"></i> Facebook</label><input type="url" name="facebook_url" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('facebook_url', '')); ?>" placeholder="https://facebook.com/yourpage"></div>
                            <div class="form-group"><label><i class="fab fa-instagram"></i> Instagram</label><input type="url" name="instagram_url" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('instagram_url', '')); ?>" placeholder="https://instagram.com/yourusername"></div>
                            <div class="form-group"><label><i class="fab fa-twitter"></i> Twitter/X</label><input type="url" name="twitter_url" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('twitter_url', '')); ?>" placeholder="https://twitter.com/yourusername"></div>
                            <div class="form-group"><label><i class="fab fa-pinterest"></i> Pinterest</label><input type="url" name="pinterest_url" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('pinterest_url', '')); ?>" placeholder="https://pinterest.com/yourusername"></div>
                            <div class="form-group"><label><i class="fab fa-youtube"></i> YouTube</label><input type="url" name="youtube_url" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('youtube_url', '')); ?>" placeholder="https://youtube.com/@yourchannel"></div>
                            <div class="form-group"><label><i class="fab fa-spotify"></i> Spotify</label><input type="url" name="spotify_url" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('spotify_url', '')); ?>" placeholder="https://open.spotify.com/user/..."></div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Social Links</button>
                        </div>
                    </form>
                </div>
                
                <!-- Logo & Favicon Panel -->
                <div id="logo-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Logo & Favicon</h2>
                        <p>Upload your site logo and favicon</p>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_logo">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label>Current Logo</label>
                                <div style="display: flex; gap: 20px; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                                    <img src="../<?php echo htmlspecialchars(getSettingValue('site_logo', 'assets/logo/painlesslyf-logo.png')); ?>" style="max-width: 100px; max-height: 60px; object-fit: contain;">
                                    <div><?php echo basename(getSettingValue('site_logo', 'assets/logo/painlesslyf-logo.png')); ?></div>
                                </div>
                            </div>
                            <div class="form-group full-width">
                                <label>Upload New Logo</label>
                                <div class="file-upload">
                                    <input type="file" name="site_logo" id="site_logo" accept="image/*">
                                    <label for="site_logo" class="file-upload-label"><i class="fas fa-cloud-upload-alt"></i> Choose Logo Image</label>
                                </div>
                            </div>
                            <div class="form-group full-width">
                                <label>Current Favicon</label>
                                <div style="display: flex; gap: 20px; align-items: center; padding: 15px; background: #f8f9fa; border-radius: 10px;">
                                    <img src="../<?php echo htmlspecialchars(getSettingValue('favicon', 'assets/logo/painlesslyf-logo.png')); ?>" style="width: 32px; height: 32px; object-fit: contain;">
                                    <div><?php echo basename(getSettingValue('favicon', 'assets/logo/painlesslyf-logo.png')); ?></div>
                                </div>
                            </div>
                            <div class="form-group full-width">
                                <label>Upload New Favicon</label>
                                <div class="file-upload">
                                    <input type="file" name="favicon" id="favicon" accept="image/*">
                                    <label for="favicon" class="file-upload-label"><i class="fas fa-cloud-upload-alt"></i> Choose Favicon Image</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Logo & Favicon</button>
                        </div>
                    </form>
                </div>
                
                <!-- Shared Buttons Panel -->
                <div id="shared-buttons-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Shared Button Colors</h2>
                        <p>Control the common colors used by featured messages, play buttons, featured posts, article links, and post filters.</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_shared_buttons">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Button Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="shared_button_color" value="<?php echo htmlspecialchars(getSettingValue('shared_button_color', '#2563eb')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('shared_button_color', '#2563eb')); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Button Hover Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="shared_button_hover_color" value="<?php echo htmlspecialchars(getSettingValue('shared_button_hover_color', '#1d4ed8')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('shared_button_hover_color', '#1d4ed8')); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Button Text and Icon Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="shared_button_text_color" value="<?php echo htmlspecialchars(getSettingValue('shared_button_text_color', '#ffffff')); ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars(getSettingValue('shared_button_text_color', '#ffffff')); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Shared Button Colors</button>
                        </div>
                    </form>
                </div>

                <!-- Appearance Panel -->
                <div id="appearance-panel" class="settings-panel">
                    <div class="panel-header">
                        <h2>Appearance Settings</h2>
                        <p>Customize colors and custom CSS/JS</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_appearance">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Primary Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="primary_color" value="<?php echo getSettingValue('primary_color', '#2563eb'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('primary_color', '#2563eb'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-palette"></i> Theme Blue</label>
                                <div class="color-input-group">
                                    <input type="color" name="theme_blue_color" value="<?php echo getSettingValue('theme_blue_color', '#2563eb'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('theme_blue_color', '#2563eb'); ?>" readonly>
                                </div>
                                <small>Used for card text, links, and hover accents on the homepage</small>
                            </div>
                            <div class="form-group">
                                <label>Theme Blue (Dark / Hover)</label>
                                <div class="color-input-group">
                                    <input type="color" name="theme_blue_dark_color" value="<?php echo getSettingValue('theme_blue_dark_color', '#1d4ed8'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('theme_blue_dark_color', '#1d4ed8'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-leaf"></i> Theme Grass Green</label>
                                <div class="color-input-group">
                                    <input type="color" name="theme_green_color" value="<?php echo getSettingValue('theme_green_color', '#4a7c59'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('theme_green_color', '#4a7c59'); ?>" readonly>
                                </div>
                                <small>Matches logo green — used in gradients and accents</small>
                            </div>
                            <div class="form-group">
                                <label>Theme Green (Dark)</label>
                                <div class="color-input-group">
                                    <input type="color" name="theme_green_dark_color" value="<?php echo getSettingValue('theme_green_dark_color', '#2c4a3b'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('theme_green_dark_color', '#2c4a3b'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Navbar Background</label>
                                <div class="color-input-group">
                                    <input type="color" name="navbar_background" value="<?php echo getSettingValue('navbar_background', '#ffffff'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('navbar_background', '#ffffff'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Navbar Text Color</label>
                                <div class="color-input-group">
                                    <input type="color" name="navbar_text_color" value="<?php echo getSettingValue('navbar_text_color', '#333333'); ?>">
                                    <input type="text" class="form-control" value="<?php echo getSettingValue('navbar_text_color', '#333333'); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group full-width">
                                <label>Custom CSS</label>
                                <textarea name="custom_css" class="form-control" rows="5" placeholder="/* Your custom CSS here */"><?php echo htmlspecialchars(getSettingValue('custom_css', '')); ?></textarea>
                            </div>
                            <div class="form-group full-width">
                                <label>Custom JavaScript</label>
                                <textarea name="custom_js" class="form-control" rows="5" placeholder="// Your custom JavaScript here"><?php echo htmlspecialchars(getSettingValue('custom_js', '')); ?></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Appearance</button>
                            <button type="button" class="btn btn-danger" onclick="if(confirm('Reset all settings to default?')) document.getElementById('reset_form').submit();"><i class="fas fa-undo"></i> Reset All</button>
                        </div>
                    </form>
                    <form id="reset_form" method="POST" style="display: none;"><input type="hidden" name="action" value="reset_settings"></form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tabName) {
            document.querySelectorAll('.settings-panel').forEach(panel => panel.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabName + '-panel').classList.add('active');
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
        }

        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const adminSidebar = document.getElementById('adminSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            if (!adminSidebar) return;
            adminSidebar.classList.toggle('sidebar-open');
            sidebarOverlay?.classList.toggle('active');
            const icon = mobileMenuToggle?.querySelector('i');
            if (icon) {
                icon.className = adminSidebar.classList.contains('sidebar-open') ? 'fas fa-times' : 'fas fa-bars';
            }
        }

        function closeSidebar() {
            if (!adminSidebar) return;
            adminSidebar.classList.remove('sidebar-open');
            sidebarOverlay?.classList.remove('active');
            const icon = mobileMenuToggle?.querySelector('i');
            if (icon) icon.className = 'fas fa-bars';
        }

        mobileMenuToggle?.addEventListener('click', toggleSidebar);
        sidebarOverlay?.addEventListener('click', closeSidebar);
    </script>
</body>
</html>