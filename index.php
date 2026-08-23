<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db-connection.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get settings
$site_title = getSetting('site_title', 'Painlesslyf');
$site_description = getSetting('site_description', 'Painlesslyf is a faith-driven space for truth, grace, healing, and purpose—where painful seasons become a walking melody of restoration.');
$site_logo = getSetting('site_logo', 'assets/logo/painlesslyf-logo.png');
$favicon = getSetting('favicon', 'assets/logo/painlesslyf-logo.png');
$primary_color = getSetting('primary_color', '#C9A962');

// Hero slideshow settings
$hero_slideshow_enabled = getSetting('hero_slideshow_enabled', '1');
$hero_slide_1_image = getSetting('hero_slide_1_image', '');
$hero_slide_2_image = getSetting('hero_slide_2_image', '');
$hero_slide_3_image = getSetting('hero_slide_3_image', '');
$hero_slideshow_interval = getSetting('hero_slideshow_interval', '5000');
$hero_text_color = getSetting('hero_text_color', '#ffffff');
$hero_button_style = getSetting('hero_button_style', 'cream');
$hero_button_bg_color = getSetting('hero_button_bg_color', '#f5e6d3');
$hero_button_text_color = getSetting('hero_button_text_color', '#333333');
$hero_button_text = getSetting('hero_button_text', 'Explore the journey');
$hero_button_link = getSetting('hero_button_link', 'blog.php');
$hero_title = getSetting('hero_title', 'Painlesslyf');
$hero_subtitle = getSetting('hero_subtitle', 'Turn your tortuous path into a walking melody.');
$hero_badge = getSetting('hero_badge', 'Faith • Grace • Purpose • Melody');
$hero_logo_enabled = getSetting('hero_logo_enabled', '1');
$hero_title_font_weight = getSetting('hero_title_font_weight', '900');
$hero_slideshow_effect = getSetting('hero_slideshow_effect', 'slide');

// Get crawling announcements
$crawling_announcements = getSetting('crawling_announcements', 'Welcome to Painlesslyf | Faith · Grace · Purpose · Melody | Truth for the brave');
$announcement_speed = getSetting('announcement_speed', '20');

// Get card settings
$card_1_title = getSetting('card_1_title', 'Truth in the Middle of the Pain');
$card_1_description = getSetting('card_1_description', 'Real faith, honest grace, and practical wisdom for walking out of pain and back toward healing.');
$card_1_icon = getSetting('card_1_icon', 'fas fa-feather-pointed');
$card_1_link = getSetting('card_1_link', 'blog.php');

$card_2_title = getSetting('card_2_title', 'Audio Messages');
$card_2_description = getSetting('card_2_description', 'Listen to life-giving teachings designed to bring clarity, courage, and peace to your journey.');
$card_2_icon = getSetting('card_2_icon', 'fas fa-podcast');
$card_2_link = getSetting('card_2_link', 'audio.php');

$card_3_title = getSetting('card_3_title', 'The Heart Behind Painlesslyf');
$card_3_description = getSetting('card_3_description', 'A space for restoration, encouragement, and the reminder that your story can still become a beautiful melody.');
$card_3_icon = getSetting('card_3_icon', 'fas fa-heart');
$card_3_link = getSetting('card_3_link', 'about.php');

// Get cards section header settings
$cards_section_title = getSetting('cards_section_title', 'Faith · Grace · Purpose · Melody');
$cards_section_subtitle = getSetting('cards_section_subtitle', 'Painlesslyf is for the brave-hearted—people learning how to move from hurt to healing, confusion to clarity, and survival to a life that sings.');

// Cards section background settings
$cards_background_type = getSetting('cards_background_type', 'gradient');
$cards_background_color = getSetting('cards_background_color', '#faf7f0');
$cards_background_gradient_start = getSetting('cards_background_gradient_start', '#faf7f0');
$cards_background_gradient_mid = getSetting('cards_background_gradient_mid', '#f5ede0');
$cards_background_gradient_end = getSetting('cards_background_gradient_end', '#efe3d0');
$cards_background_image = getSetting('cards_background_image', '');
$cards_background_overlay = getSetting('cards_background_overlay', 'rgba(0,0,0,0)');
$cards_title_color = getSetting('cards_title_color', '#ffffff');
$cards_subtitle_color = getSetting('cards_subtitle_color', 'rgba(255,255,255,0.88)');
$cards_card_background = getSetting('cards_card_background', '#ffffff');
$cards_card_hover_background = getSetting('cards_card_hover_background', '#ffffff');
$cards_card_text_color = getSetting('cards_card_text_color', '#6b6b6b');
$cards_card_title_color = getSetting('cards_card_title_color', '#1a2744');
$cards_card_icon_color = getSetting('cards_card_icon_color', '#C9A962');
$cards_card_border_color = getSetting('cards_card_border_color', 'rgba(0,0,0,0.08)');
$cards_card_hover_border_color = getSetting('cards_card_hover_border_color', 'rgba(186,166,142,0.3)');
$cards_enable_animated_background = getSetting('cards_enable_animated_background', '0');
$cards_icon_blue_color = getSetting('cards_icon_blue_color', '#1a2744');
$theme_blue = getSetting('theme_blue_color', '#1a2744');
$theme_blue_dark = getSetting('theme_blue_dark_color', '#0f1824');
$theme_green = getSetting('theme_green_color', '#C9A962');
$theme_green_dark = getSetting('theme_green_dark_color', '#A68844');

// BLOG SECTION SETTINGS
$blog_section_title = getSetting('blog_section_title', 'Latest from the journey');
$blog_section_subtitle = getSetting('blog_section_subtitle', 'Stories, reflections, and teachings for turning pain into purpose and purpose into peace.');
$blog_background_type = getSetting('blog_background_type', 'solid');
$blog_background_color = getSetting('blog_background_color', '#ffffff');
$blog_background_gradient_start = getSetting('blog_background_gradient_start', '#ffffff');
$blog_background_gradient_end = getSetting('blog_background_gradient_end', '#faf7f0');
$blog_background_image = getSetting('blog_background_image', '');
$blog_background_overlay = getSetting('blog_background_overlay', 'rgba(0,0,0,0.3)');
$blog_title_color = getSetting('blog_title_color', '#ffffff');
$blog_subtitle_color = getSetting('blog_subtitle_color', 'rgba(255,255,255,0.88)');
$blog_card_background = getSetting('blog_card_background', '#ffffff');
$blog_card_hover_background = getSetting('blog_card_hover_background', '#ffffff');
$blog_card_text_color = getSetting('blog_card_text_color', '#6b6b6b');
$blog_card_title_color = getSetting('blog_card_title_color', '#1a2744');
$blog_card_button_color = getSetting('blog_card_button_color', '#C9A962');
$blog_card_button_hover_color = getSetting('blog_card_button_hover_color', '#A68844');
$blog_card_shadow = getSetting('blog_card_shadow', '0 5px 15px rgba(0,0,0,0.05)');
$blog_card_hover_shadow = getSetting('blog_card_hover_shadow', '0 15px 30px rgba(0,0,0,0.1)');
$blog_enable_animated_background = getSetting('blog_enable_animated_background', '0');

// Shared wallpaper for cards + blog sections (single fixed background while scrolling)
$shared_wallpaper_image = getSetting('shared_wallpaper_image', '');
$shared_wallpaper_overlay_color = getSetting('shared_wallpaper_overlay_color', '#faf7f0');
$shared_wallpaper_overlay_opacity = getSetting('shared_wallpaper_overlay_opacity', '0.2');
$shared_wallpaper_card_opacity = getSetting('shared_wallpaper_card_opacity', '0.94');

if (empty($shared_wallpaper_image)) {
    if (!empty($cards_background_image) && file_exists($cards_background_image)) {
        $shared_wallpaper_image = $cards_background_image;
    } elseif (!empty($blog_background_image) && file_exists($blog_background_image)) {
        $shared_wallpaper_image = $blog_background_image;
    } else {
        $shared_wallpaper_image = 'assets/images/shared-wallpaper.svg';
    }
}

function hexToRgba($hex, $opacity) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba({$r}, {$g}, {$b}, {$opacity})";
}

$shared_wallpaper_overlay = hexToRgba($shared_wallpaper_overlay_color, floatval($shared_wallpaper_overlay_opacity));

// Footer settings (styles handled by includes/footer.php)
$footer_copyright_text = getSetting('footer_copyright', '© 2026 Painlesslyf. All rights reserved.');

// Get recent posts
$recent_posts = getRecentPosts(3);

// Social media links
$social_links = [
    'instagram' => getSetting('instagram_url', '#'),
    'twitter' => getSetting('twitter_url', '#'),
    'youtube' => getSetting('youtube_url', '#'),
    'spotify' => getSetting('spotify_url', '#'),
    'pinterest' => getSetting('pinterest_url', '#'),
    'facebook' => getSetting('facebook_url', '#')
];

// Helper function to get full image URL
function getImageUrl($image_path) {
    if (empty($image_path)) {
        return 'assets/images/default-post.jpg';
    }
    
    if (preg_match('/^https?:\/\//', $image_path)) {
        return $image_path;
    }
    
    if (strpos($image_path, 'assets/') === 0 || strpos($image_path, 'uploads/') === 0) {
        return $image_path;
    }
    
    return 'uploads/images/' . $image_path;
}

$card_icon_fallbacks = ['fas fa-feather-pointed', 'fas fa-podcast', 'fas fa-compass'];

function renderCardIcon($icon, $index = 0) {
    global $card_icon_fallbacks;
    $icon = trim($icon);
    if (preg_match('/^(fas|far|fab|fal|fad)\s+fa-/', $icon)) {
        return '<i class="' . htmlspecialchars($icon) . '" aria-hidden="true"></i>';
    }
    $fallback = $card_icon_fallbacks[$index] ?? 'fas fa-star';
    return '<i class="' . htmlspecialchars($fallback) . '" aria-hidden="true"></i>';
}

// Helper functions for backgrounds
function getCardsBackgroundStyle() {
    global $cards_background_type, $cards_background_color, $cards_background_gradient_start, 
           $cards_background_gradient_mid, $cards_background_gradient_end, $cards_background_image;
    
    switch($cards_background_type) {
        case 'solid':
            return "background-color: {$cards_background_color};";
        case 'gradient':
            return "background: linear-gradient(135deg, {$cards_background_gradient_start} 0%, {$cards_background_gradient_mid} 50%, {$cards_background_gradient_end} 100%);";
        case 'image':
            if (!empty($cards_background_image) && file_exists($cards_background_image)) {
                return "background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('{$cards_background_image}'); background-size: cover; background-position: center; background-attachment: fixed;";
            }
            return "background-color: {$cards_background_color};";
        default:
            return "background-color: #faf7f0;";
    }
}

function getBlogBackgroundStyle() {
    global $blog_background_type, $blog_background_color, $blog_background_gradient_start, 
           $blog_background_gradient_end, $blog_background_image, $blog_background_overlay;
    
    switch($blog_background_type) {
        case 'solid':
            return "background-color: {$blog_background_color};";
        case 'gradient':
            return "background: linear-gradient(135deg, {$blog_background_gradient_start} 0%, {$blog_background_gradient_end} 100%);";
        case 'image':
            if (!empty($blog_background_image) && file_exists($blog_background_image)) {
                $overlay_value = floatval($blog_background_overlay);
                return "background: linear-gradient(rgba(0,0,0,{$overlay_value}), rgba(0,0,0,{$overlay_value})), url('{$blog_background_image}'); background-size: cover; background-position: center; background-attachment: fixed;";
            }
            return "background-color: {$blog_background_color};";
        default:
            return "background-color: #ffffff;";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo $site_title; ?> - Faith, Grace, Purpose & Melody</title>
    
    <meta name="description" content="<?php echo $site_description; ?>">
    <meta name="keywords" content="marriage, faith, grace, Christian blog, divine assignment, painless life, truth">
    
    <meta property="og:title" content="<?php echo $site_title; ?>">
    <meta property="og:description" content="<?php echo $site_description; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/<?php echo $site_logo; ?>">
    
    <link rel="icon" type="image/png" href="<?php echo $favicon; ?>">
    <link rel="apple-touch-icon" href="<?php echo $site_logo; ?>">
    <link rel="manifest" href="manifest.json">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Inter:wght@300;400;500;600;700;800&family=Montserrat:wght@700;800;900&family=Playfair+Display:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <?php echo getCustomCSS(); ?>
    
    <style>
        :root {
            --primary: <?php echo $primary_color; ?>;
            --primary-dark: #A68844;
            --primary-light: #D4BC7A;
            --navy: #1a2744;
            --navy-dark: #0f1824;
            --gold: #C9A962;
            --gold-dark: #A68844;
            --theme-blue: <?php echo $theme_blue; ?>;
            --theme-blue-dark: <?php echo $theme_blue_dark; ?>;
            --theme-green: <?php echo $theme_green; ?>;
            --theme-green-dark: <?php echo $theme_green_dark; ?>;
            --text-dark: #1a2744;
            --text-light: #5a6478;
            --bg-light: #FAF7F0;
            --white: #ffffff;
            --shadow-sm: 0 4px 20px rgba(26, 39, 68, 0.05);
            --shadow-md: 0 8px 30px rgba(26, 39, 68, 0.08);
            --shadow-lg: 0 20px 40px rgba(26, 39, 68, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
            background: var(--bg-light);
            color: var(--text-dark);
        }
        
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        /* Hero Section - full 1920x1080 aspect ratio */
        .hero-slideshow {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            max-height: 100vh;
            min-height: 420px;
            overflow: hidden;
            margin-top: -80px;
        }
        
        .slides-track {
            display: flex;
            height: 100%;
            width: 100%;
            transition: transform 0.85s cubic-bezier(0.65, 0, 0.35, 1);
            will-change: transform;
        }
        
        .hero-slideshow.effect-fade .slides-track {
            display: block;
            transform: none !important;
            transition: none;
        }
        
        .hero-slideshow.effect-fade .slide {
            position: absolute;
            inset: 0;
            flex: none;
            width: 100%;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.9s ease;
        }
        
        .hero-slideshow.effect-fade .slide.active {
            opacity: 1;
            visibility: visible;
        }
        
        .slide {
            flex: 0 0 100%;
            width: 100%;
            min-height: 100%;
            position: relative;
        }
        
        .slide-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        .slide-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: rgba(0,0,0,0.3);
        }
        
        .hero-content-inner {
            max-width: 900px;
            padding: 0 20px;
        }

        .hero-logo {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.95);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            margin: 0 auto 24px;
            display: block;
            background: #ffffff;
        }
        
        .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 30px;
            letter-spacing: 1px;
            color: white;
        }
        
        .hero-title {
            font-size: clamp(40px, 7vw, 92px);
            font-weight: 900;
            line-height: 0.92;
            margin-bottom: 18px;
            font-family: 'Montserrat', 'Arial Black', sans-serif;
            color: white;
            letter-spacing: 0.02em;
            text-transform: none;
            text-shadow: 0 6px 28px rgba(0, 0, 0, 0.52), 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .hero-subtitle {
            font-size: 20px;
            line-height: 1.6;
            margin-bottom: 40px;
            opacity: 0.9;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            color: white;
        }
        
        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn-primary-custom {
            background: var(--white);
            color: var(--text-dark);
            padding: 14px 36px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-outline-custom {
            background: transparent;
            border: 2px solid var(--white);
            color: var(--white);
            padding: 14px 36px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-outline-custom:hover {
            background: var(--white);
            color: var(--text-dark);
            transform: translateY(-3px);
        }
        
        .btn-secondary-custom {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
            color: var(--white);
            padding: 14px 36px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-secondary-custom:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-3px);
        }
        
        .slideshow-nav {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 15px;
            z-index: 10;
        }
        
        .nav-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .nav-dot.active {
            background: <?php echo $hero_button_bg_color; ?>;
            transform: scale(1.2);
        }
        
        .slideshow-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(5px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            z-index: 10;
            font-size: 20px;
            transition: all 0.3s ease;
        }
        
        .slideshow-arrow:hover {
            background: <?php echo $hero_button_bg_color; ?>;
            color: <?php echo $hero_button_text_color; ?>;
        }
        
        .slideshow-arrow.prev {
            left: 20px;
        }
        
        .slideshow-arrow.next {
            right: 20px;
        }
        
        /* Ticker */
        .ticker-section {
            background: var(--white);
            padding: 14px 0;
            overflow: hidden;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            box-shadow: var(--shadow-sm);
        }
        
        .ticker-container {
            display: flex;
            animation: ticker <?php echo $announcement_speed; ?>s linear infinite;
            white-space: nowrap;
        }
        
        .ticker-container:hover {
            animation-play-state: paused;
        }
        
        .ticker-item {
            padding: 0 30px;
            border-right: 2px solid var(--theme-blue);
            font-weight: 600;
            color: var(--theme-blue);
        }
        
        @keyframes ticker {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        
        /* Shared wallpaper zone — cards + blog scroll over one fixed background */
        .shared-wallpaper-zone {
            position: relative;
            isolation: isolate;
        }
        
        .shared-wallpaper-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('<?php echo htmlspecialchars($shared_wallpaper_image); ?>');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            z-index: 0;
        }
        
        .shared-wallpaper-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background: <?php echo $shared_wallpaper_overlay; ?>;
        }
        
        .shared-wallpaper-zone > section {
            position: relative;
            z-index: 1;
            background: transparent !important;
        }
        
        /* Cards Section */
        .cards-section {
            padding: 80px 0 100px;
            position: relative;
        }
        
        .cards-section .section-title,
        .blog-section .section-title {
            color: #ffffff;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.25);
        }
        
        .cards-section .section-subtitle,
        .blog-section .section-subtitle {
            color: rgba(255, 255, 255, 0.88);
        }
        
        .section-title {
            font-size: 42px;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            text-align: center;
            margin-bottom: 16px;
        }
        
        .section-subtitle {
            font-size: 18px;
            text-align: center;
            max-width: 700px;
            margin: 0 auto 40px;
        }
        
        .card-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .priority-card {
            background: rgba(255, 255, 255, <?php echo floatval($shared_wallpaper_card_opacity); ?>);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 24px;
            padding: 40px 28px;
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 8px 32px rgba(30, 77, 114, 0.08);
        }
        
        .priority-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.98);
            border-color: rgba(201, 169, 98, 0.4);
            box-shadow: 0 20px 50px rgba(26, 39, 68, 0.12);
        }
        
        .priority-card .card-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(26, 39, 68, 0.08) 0%, rgba(201, 169, 98, 0.12) 100%);
            border: 1px solid rgba(201, 169, 98, 0.3);
            font-size: 28px;
            color: var(--navy);
            transition: var(--transition);
        }
        
        .priority-card:hover .card-icon {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-dark) 100%);
            color: var(--gold);
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(26, 39, 68, 0.35);
        }
        
        .cards-section .priority-card h3 {
            font-size: 22px;
            margin-bottom: 14px;
            color: var(--navy);
            font-weight: 700;
            font-family: 'Playfair Display', serif;
        }
        
        .cards-section .priority-card p {
            color: var(--navy);
            opacity: 0.85;
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 15px;
        }
        
        .cards-section .card-link {
            color: var(--theme-blue);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 15px;
        }
        
        .cards-section .card-link:hover {
            gap: 12px;
            color: var(--theme-blue-dark);
        }
        
        .card-link {
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 15px;
        }
        
        /* Blog Section */
        .blog-section {
            padding: 100px 0 80px;
            position: relative;
        }
        
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        
        .blog-card {
            background: rgba(255, 255, 255, <?php echo floatval($shared_wallpaper_card_opacity); ?>);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 0;
            overflow: hidden;
            transition: var(--transition);
            box-shadow: <?php echo $blog_card_shadow; ?>;
            border: 1px solid rgba(201, 169, 98, 0.2);
        }
        
        .blog-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(26, 39, 68, 0.25);
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-dark) 100%);
            border-color: var(--gold);
        }
        
        .blog-card:hover .blog-title,
        .blog-card:hover .blog-excerpt,
        .blog-card:hover .card-link {
            color: #ffffff !important;
        }
        
        .blog-card:hover .card-link i {
            color: #ffffff;
        }
        
        .blog-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            height: auto;
            object-fit: cover;
            border-radius: 0;
            display: block;
        }
        
        .blog-content {
            padding: 24px;
        }
        
        .blog-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 10px;
            color: <?php echo $blog_card_title_color; ?>;
            line-height: 1.4;
            font-family: 'Playfair Display', serif;
        }
        
        .blog-excerpt {
            color: <?php echo $blog_card_text_color; ?>;
            line-height: 1.6;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .blog-section .card-link {
            color: var(--gold);
        }
        
        .blog-section .card-link:hover {
            color: var(--gold-dark);
        }
        
        .blog-section .btn-outline {
            background: transparent;
            color: #ffffff;
            border: 2px solid rgba(255, 255, 255, 0.85);
            padding: 10px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 14px;
        }
        
        .blog-section .btn-outline:hover {
            background: var(--theme-blue);
            border-color: var(--theme-blue);
            color: var(--white);
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.3);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 10px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            font-size: 14px;
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-3px);
        }
        
        .text-center {
            text-align: center;
        }
        
        .mt-4 {
            margin-top: 40px;
        }
        
        /* Responsive Design */
        @media (max-width: 1024px) {
            .container {
                padding: 0 20px;
            }
            
            .hero-title {
                font-size: clamp(36px, 6vw, 56px);
            }
            
            .shared-wallpaper-bg {
                background-attachment: scroll;
            }
            
            .section-title {
                font-size: 36px;
            }
            
            .card-grid,
            .blog-grid,
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 25px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding-top: 60px;
            }
            
            .hero-slideshow {
                aspect-ratio: auto;
                height: 70vh;
                min-height: 380px;
                max-height: 85vh;
                margin-top: -60px;
            }
            
            .hero-title {
                font-size: clamp(32px, 8vw, 44px);
                font-weight: <?php echo intval($hero_title_font_weight); ?>;
            }
            
            .hero-subtitle {
                font-size: 16px;
                margin-bottom: 25px;
            }
            
            .hero-badge {
                font-size: 12px;
                padding: 6px 16px;
                margin-bottom: 20px;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
                gap: 12px;
            }
            
            .btn-primary-custom,
            .btn-outline-custom,
            .btn-secondary-custom {
                width: 220px;
                justify-content: center;
                padding: 12px 24px;
            }
            
            .slideshow-arrow {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .hero-logo {
                width: 110px;
                height: 110px;
            }
            
            .cards-section,
            .blog-section {
                padding: 60px 0;
            }
            
            .section-title {
                font-size: 28px;
            }
            
            .section-subtitle {
                font-size: 15px;
                margin-bottom: 30px;
            }
            
            .card-grid,
            .blog-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .priority-card {
                padding: 28px 20px;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 0 16px;
            }
            
            .hero-slideshow {
                min-height: 320px;
            }
            
            .hero-logo {
                width: 90px;
                height: 90px;
            }
            
            .hero-title {
                font-size: 28px;
            }
            
            .hero-subtitle {
                font-size: 14px;
            }
            
            .btn-primary-custom,
            .btn-outline-custom,
            .btn-secondary-custom {
                width: 200px;
                padding: 10px 20px;
                font-size: 13px;
            }
            
            .hero-title {
                font-size: clamp(28px, 9vw, 36px);
            }
            
            .section-title {
                font-size: 24px;
            }
            
            .priority-card {
                padding: 24px 16px;
            }
        }
        }
        
        @media (hover: none) and (pointer: coarse) {
            .blog-card:active {
                transform: translateY(-4px);
                background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            }
            
            .blog-card:active .blog-title,
            .blog-card:active .blog-excerpt,
            .blog-card:active .card-link {
                color: #ffffff !important;
            }
            
            .blog-section .btn-outline:active {
                background: var(--theme-blue);
                border-color: var(--theme-blue);
                color: var(--white);
            }
            
            .btn-primary-custom,
            .btn-outline-custom,
            .btn-secondary-custom,
            .card-link,
            .footer-social a {
                min-height: 44px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Hero Slideshow -->
    <section class="hero-slideshow<?php echo $hero_slideshow_effect === 'fade' ? ' effect-fade' : ''; ?>">
        <?php
        $slides = [];
        for ($i = 1; $i <= 3; $i++) {
            $image = getSetting("hero_slide_{$i}_image", '');
            if (!empty($image)) {
                $slides[] = $image;
            }
        }
        
        if (empty($slides)) {
            $slides = ['assets/images/default-hero.jpg', 'assets/images/default-hero-2.jpg'];
        }
        ?>
        
        <div class="slides-track" id="slidesTrack">
        <?php foreach ($slides as $index => $slide_image): ?>
        <div class="slide<?php echo ($hero_slideshow_effect === 'fade' && $index === 0) ? ' active' : ''; ?>">
            <div class="slide-background" style="background-image: url('<?php echo $slide_image; ?>');"></div>
            <div class="slide-content">
                <div class="hero-content-inner">
                    <?php if ($hero_logo_enabled == '1'): ?>
                    <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_title); ?> Logo" class="hero-logo" data-aos="fade-up">
                    <?php endif; ?>
                    <span class="hero-badge" data-aos="fade-up"><?php echo htmlspecialchars($hero_badge); ?></span>
                    <h1 class="hero-title" data-aos="fade-up" data-aos-delay="100"><?php echo htmlspecialchars($hero_title); ?></h1>
                    <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="200"><?php echo htmlspecialchars($hero_subtitle); ?></p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        
        <?php if (count($slides) > 1): ?>
        <div class="slideshow-arrow prev" onclick="changeSlide(-1)"><i class="fas fa-chevron-left"></i></div>
        <div class="slideshow-arrow next" onclick="changeSlide(1)"><i class="fas fa-chevron-right"></i></div>
        
        <div class="slideshow-nav">
            <?php foreach ($slides as $index => $slide): ?>
            <div class="nav-dot <?php echo $index === 0 ? 'active' : ''; ?>" onclick="goToSlide(<?php echo $index; ?>)"></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    
    <!-- Ticker -->
    <div class="ticker-section">
        <div class="ticker-container">
            <?php
            $announcements = explode('|', $crawling_announcements);
            for ($i = 0; $i < 3; $i++):
                foreach ($announcements as $announcement):
                    if (!empty(trim($announcement))):
            ?>
            <div class="ticker-item">
                <i class="fas fa-heart" style="color: var(--theme-blue); margin-right: 8px;"></i>
                <?php echo htmlspecialchars(trim($announcement)); ?>
            </div>
            <?php
                    endif;
                endforeach;
            endfor;
            ?>
        </div>
    </div>
    
    <!-- Shared wallpaper: Latest from the blog + Explore the journey -->
    <div class="shared-wallpaper-zone">
        <div class="shared-wallpaper-bg" aria-hidden="true"></div>
    
    <!-- Blog Section -->
    <?php if (!empty($recent_posts)): ?>
    <section class="blog-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up"><?php echo htmlspecialchars($blog_section_title); ?></h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100"><?php echo htmlspecialchars($blog_section_subtitle); ?></p>
            
            <div class="blog-grid">
                <?php foreach ($recent_posts as $index => $post): ?>
                <div class="blog-card" data-aos="fade-up" data-aos-delay="<?php echo 200 + ($index * 100); ?>">
                    <img src="<?php echo getImageUrl($post['featured_image'] ?? ''); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="blog-image">
                    <div class="blog-content">
                        <h3 class="blog-title"><?php echo htmlspecialchars($post['title']); ?></h3>
                        <p class="blog-excerpt"><?php echo truncateText($post['excerpt'] ?: $post['content'], 120); ?></p>
                        <a href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>" class="card-link">Read more <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="blog.php" class="btn-outline">View all posts <i class="fas fa-long-arrow-alt-right"></i></a>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Cards Section -->
    <section class="cards-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up"><?php echo htmlspecialchars($cards_section_title); ?></h2>
            <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">
                <?php echo htmlspecialchars($cards_section_subtitle); ?>
            </p>
            
            <div class="card-grid">
                <div class="priority-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-icon"><?php echo renderCardIcon($card_1_icon, 0); ?></div>
                    <h3><?php echo htmlspecialchars($card_1_title); ?></h3>
                    <p><?php echo htmlspecialchars($card_1_description); ?></p>
                    <a href="<?php echo $card_1_link; ?>" class="card-link">Read the blog <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="priority-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="card-icon"><?php echo renderCardIcon($card_2_icon, 1); ?></div>
                    <h3><?php echo htmlspecialchars($card_2_title); ?></h3>
                    <p><?php echo htmlspecialchars($card_2_description); ?></p>
                    <a href="<?php echo $card_2_link; ?>" class="card-link">Listen now <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="priority-card" data-aos="fade-up" data-aos-delay="600">
                    <div class="card-icon"><?php echo renderCardIcon($card_3_icon, 2); ?></div>
                    <h3><?php echo htmlspecialchars($card_3_title); ?></h3>
                    <p><?php echo htmlspecialchars($card_3_description); ?></p>
                    <a href="<?php echo $card_3_link; ?>" class="card-link">About me <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>
    
    </div><!-- /.shared-wallpaper-zone -->
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/main.js"></script>
    <?php echo getCustomJS(); ?>
    
    <script>
        AOS.init({
            once: true,
            duration: 800,
            offset: 100,
            disable: window.innerWidth < 768 ? 'phone' : false
        });
        
        // Slideshow — horizontal slide (left to right) or fade
        let currentSlide = 0;
        const slidesTrack = document.getElementById('slidesTrack');
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.nav-dot');
        const intervalTime = <?php echo $hero_slideshow_interval; ?>;
        const slideEffect = <?php echo json_encode($hero_slideshow_effect); ?>;
        let slideInterval;
        
        function goToSlide(index) {
            if (slides.length <= 1) return;
            clearInterval(slideInterval);
            currentSlide = ((index % slides.length) + slides.length) % slides.length;
            
            if (slideEffect === 'fade') {
                slides.forEach((slide, i) => {
                    slide.classList.toggle('active', i === currentSlide);
                });
            } else if (slidesTrack) {
                slidesTrack.style.transform = 'translateX(-' + (currentSlide * 100) + '%)';
            }
            
            updateDots();
            startSlideInterval();
        }
        
        function changeSlide(direction) {
            goToSlide(currentSlide + direction);
        }
        
        function updateDots() {
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
            });
        }
        
        function startSlideInterval() {
            if (slides.length > 1) {
                slideInterval = setInterval(() => changeSlide(1), intervalTime);
            }
        }
        
        if (slides.length > 0) {
            goToSlide(0);
        }
        
        // Ticker pause on hover
        const ticker = document.querySelector('.ticker-container');
        if (ticker) {
            ticker.addEventListener('mouseenter', () => ticker.style.animationPlayState = 'paused');
            ticker.addEventListener('mouseleave', () => ticker.style.animationPlayState = 'running');
        }
        
        // Touch optimization - disable hover effects on mobile
        if ('ontouchstart' in window) {
            document.querySelectorAll('.priority-card, .blog-card').forEach(el => {
                el.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        }
    </script>
</body>
</html>