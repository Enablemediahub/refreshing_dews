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
$site_description = getSetting('site_description', 'Truth, grace, and the roadmap back to God\'s heart for your life and your marriage.');
$site_logo = getSetting('site_logo', 'assets/logo/painlesslyf-logo.png');
$favicon = getSetting('favicon', 'assets/logo/painlesslyf-logo.png');
$primary_color = getSetting('primary_color', '#C9A962');
$font_family = getSetting('font_family', 'Inter, sans-serif');
$enable_animated_bg = getSetting('enable_animated_background', '0');

// Theme colors (blue & green — matches homepage, blog, and audio)
$theme_blue = getSetting('theme_blue_color', '#1a2744');
$theme_blue_dark = getSetting('theme_blue_dark_color', '#0f1824');
$theme_green = getSetting('theme_green_color', '#C9A962');
$theme_green_dark = getSetting('theme_green_dark_color', '#A68844');

// Get about page content from settings
$about_title = getSetting('about_title', 'About Me');
$about_subtitle = getSetting('about_subtitle', 'Faith · Grace · Purpose · Melody');
$about_hero_text_color = getSetting('about_hero_text_color', '#ffffff');
$about_profile_image = getSetting('about_profile_image', 'assets/images/profile.jpg');
$about_name = getSetting('about_name', '');
$about_role = getSetting('about_role', 'Writer & Marriage Advocate');
$about_bio = getSetting('about_bio', 'Welcome, friend. If you are here, you likely know that life is tortuous. It twists, it turns, and it often leaves us breathless.');
$about_long_bio = getSetting('about_long_bio', 'Welcome, friend.\n\nIf you are here, you likely know that life is tortuous. It twists, it turns, and it often leaves us breathless. I have walked those sharp curves more times than I care to count, and I have learned this: the journey only becomes unbearably painful when disobedience—whether willful or born of ignorance—takes the lead.\n\nBut here is what I want you to hear, deep in your spirit: A painless life is not far fetched, neither is it chimerical. It is practical. It is not a fantasy reserved for the "perfect" Christians. It is an attainable reality when we stop fighting the map and allow God to navigate.\n\nI have made mistakes. Multiplications of them at every phase of my life. Some were small missteps. Others were catastrophic detours. I carry the scars, but I also carry the wisdom. And I would hate—truly hate—to see another woman take my path or anything resembling it.\n\nThat is why I speak pure, unvarnished truth. Not to shame, but to illuminate.\n\nI am passionate about marriage. I want to challenge the norms, ask the hard questions, and inspire young women to see marriage not as a fairytale, but as a divine assignment. Let\'s understand exactly what we got ourselves into. Let\'s stop romanticizing the wedding and start stewarding the covenant. And most importantly, let\'s realign our agendas to match God\'s original design—because that is where the painless, practical peace begins.\n\nThis is a space for the brave. No sugarcoating. No fluff. Just truth, grace, and the roadmap back to God\'s heart for your life and your marriage.');
$about_signature = getSetting('about_signature', 'With truth and grace,');
$about_signature_name = getSetting('about_signature_name', '');
$about_background_color = getSetting('about_background_color', '#FAF7F0');
$about_text_color = getSetting('about_text_color', '#1a2744');
$about_accent_color = getSetting('about_accent_color', $theme_blue);
$about_cta_enabled = getSetting('about_cta_enabled', '1');

// Hero background settings
$about_hero_background_type = getSetting('about_hero_background_type', 'gradient');
$about_hero_background_solid = getSetting('about_hero_background_solid', $theme_green);
$about_hero_background_gradient_start = getSetting('about_hero_background_gradient_start', $theme_blue);
$about_hero_background_gradient_end = getSetting('about_hero_background_gradient_end', $theme_green);
$about_hero_background_image = getSetting('about_hero_background_image', '');
$about_hero_background_overlay = getSetting('about_hero_background_overlay', '0.6');

// Typography settings
$about_heading_font = getSetting('about_heading_font', 'Playfair Display');
$about_body_font = getSetting('about_body_font', 'Inter');
$about_heading_size = getSetting('about_heading_size', '42');
$about_body_size = getSetting('about_body_size', '16');

// Get social media links - ONLY Facebook, Pinterest, Instagram
$social_links = [
    'facebook' => getSetting('facebook_url', '#'),
    'instagram' => getSetting('instagram_url', '#'),
    'pinterest' => getSetting('pinterest_url', '#')
];

// Get stats
$posts_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM posts WHERE status = 'published'");
if ($result) {
    $row = $result->fetch_assoc();
    $posts_count = $row['count'];
}

$audio_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM audio_messages WHERE status = 'published'");
if ($result) {
    $row = $result->fetch_assoc();
    $audio_count = $row['count'];
}

// Get years of experience (based on first post date)
$years_exp = 3; // Default
$result = $conn->query("SELECT created_at FROM posts WHERE status = 'published' ORDER BY created_at ASC LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $first_date = new DateTime($row['created_at']);
    $now = new DateTime();
    $interval = $first_date->diff($now);
    $years_exp = max(1, $interval->y);
}

// Get recent posts for sidebar
$recent_posts = getRecentPosts(3);

// Get recent audio for sidebar
$recent_audio = getRecentAudio(3);

// Function to get hero background style (matches audio page pattern)
function getAboutHeaderStyle() {
    global $about_hero_background_type, $about_hero_background_solid,
           $about_hero_background_gradient_start, $about_hero_background_gradient_end,
           $about_hero_background_image, $about_hero_background_overlay;

    switch ($about_hero_background_type) {
        case 'solid':
            return "background-color: {$about_hero_background_solid};";
        case 'gradient':
            return "background: linear-gradient(135deg, {$about_hero_background_gradient_start} 0%, {$about_hero_background_gradient_end} 100%);";
        case 'image':
            if (!empty($about_hero_background_image)) {
                $overlay = is_numeric($about_hero_background_overlay) ? max(0, min(1, (float) $about_hero_background_overlay)) : 0.6;
                return "background: linear-gradient(rgba(0,0,0,{$overlay}), rgba(0,0,0,{$overlay})), url('{$about_hero_background_image}'); background-size: cover; background-position: center;";
            }
            return "background: linear-gradient(135deg, {$about_hero_background_gradient_start} 0%, {$about_hero_background_gradient_end} 100%);";
        default:
            global $theme_blue, $theme_green;
            return "background: linear-gradient(135deg, {$theme_blue} 0%, {$theme_green} 100%);";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>About - <?php echo htmlspecialchars($site_title); ?></title>
    
    <!-- Meta Tags -->
    <meta name="description" content="Learn more about <?php echo htmlspecialchars($about_name); ?>, the creator behind <?php echo htmlspecialchars($site_title); ?>">
    <meta name="keywords" content="about, biography, creator, author, story, personal">
    <meta name="author" content="<?php echo htmlspecialchars($site_title); ?>">
    
    <!-- Open Graph Tags -->
    <meta property="og:title" content="About - <?php echo htmlspecialchars($site_title); ?>">
    <meta property="og:description" content="Learn more about the person behind the words">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/<?php echo $about_profile_image; ?>">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/about.php">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="About - <?php echo htmlspecialchars($site_title); ?>">
    <meta name="twitter:description" content="Learn more about the person behind the words">
    <meta name="twitter:image" content="<?php echo SITE_URL; ?>/<?php echo $about_profile_image; ?>">
    
    <!-- PWA / Installable App Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo htmlspecialchars($site_title); ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="<?php echo $primary_color; ?>" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="<?php echo $primary_color; ?>" media="(prefers-color-scheme: dark)">
    <meta name="msapplication-TileColor" content="<?php echo $primary_color; ?>">
    <meta name="application-name" content="<?php echo htmlspecialchars($site_title); ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $favicon; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $favicon; ?>">
    <link rel="shortcut icon" href="<?php echo $favicon; ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $site_logo; ?>">
    <link rel="apple-touch-icon" href="<?php echo $site_logo; ?>">
    
    <!-- Web App Manifest -->
    <link rel="manifest" href="/painlesslyf/manifest.json">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <?php echo getPageHeroStyles(); ?>
    
    <style>
        :root {
            --theme-blue: <?php echo $theme_blue; ?>;
            --theme-blue-dark: <?php echo $theme_blue_dark; ?>;
            --theme-green: <?php echo $theme_green; ?>;
            --theme-green-dark: <?php echo $theme_green_dark; ?>;
            --primary: <?php echo $about_accent_color; ?>;
            --dark: <?php echo $about_text_color; ?>;
            --light: <?php echo $about_background_color; ?>;
            --gray: #6b6b6b;
            --border: rgba(37, 99, 235, 0.12);
            --shadow-sm: 0 4px 20px rgba(37, 99, 235, 0.08);
            --shadow-md: 0 8px 30px rgba(37, 99, 235, 0.12);
            --shadow-lg: 0 20px 50px rgba(37, 99, 235, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --font-family: <?php echo $about_body_font; ?>, sans-serif;
            --heading-font: <?php echo $about_heading_font; ?>, serif;
            --heading-size: <?php echo $about_heading_size; ?>px;
            --body-size: <?php echo $about_body_size; ?>px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            font-size: var(--body-size);
            color: var(--dark);
            background: var(--light);
            line-height: 1.6;
            overflow-x: hidden;
        }

        h1, h2, h3, .about-title, .profile-name, .signature-name, .about-title {
            font-family: var(--heading-font);
        }

        .about-title {
            font-size: var(--heading-size);
        }

        .container {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Hero Section — dimensions via getPageHeroStyles(); background set per page */
        .about-hero {
            color: <?php echo $about_hero_text_color; ?>;
            <?php echo getAboutHeaderStyle(); ?>
            text-align: center;
        }

        .about-hero h1 {
            animation: fadeInUp 1s ease;
        }

        .about-hero p {
            animation: fadeInUp 1s ease 0.2s both;
        }

        /* Main About Section */
        .about-main {
            padding: 80px 0;
            background: <?php echo $about_background_color; ?>;
        }

        .about-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
            align-items: start;
        }

        @media (min-width: 992px) {
            .about-grid {
                grid-template-columns: 1fr 2fr;
                gap: 50px;
            }
        }

        /* Profile Card */
        .profile-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.9);
            transition: var(--transition);
            max-width: 500px;
            margin: 0 auto;
            width: 100%;
        }

        @media (min-width: 992px) {
            .profile-card {
                position: sticky;
                top: 90px;
                max-width: none;
            }
        }

        .profile-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 24px 60px rgba(37, 99, 235, 0.18);
            border-color: rgba(37, 99, 235, 0.25);
        }

        .profile-image-wrapper {
            position: relative;
            min-height: 300px;
            aspect-ratio: 4 / 5;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
        }

        @media (min-width: 768px) {
            .profile-image-wrapper {
                min-height: 400px;
            }
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center top;
            transition: transform 0.5s ease;
        }

        @media (max-width: 767px) {
            .profile-image {
                object-fit: contain;
            }
        }

        .profile-card:hover .profile-image {
            transform: scale(1.05);
        }

        .profile-image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(to top, rgba(0,0,0,0.5), transparent);
        }

        .profile-info {
            padding: 25px;
            text-align: center;
        }

        @media (min-width: 768px) {
            .profile-info {
                padding: 30px;
            }
        }

        .profile-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--theme-blue);
            margin-bottom: 5px;
            font-family: var(--heading-font);
        }

        @media (min-width: 768px) {
            .profile-name {
                font-size: 28px;
            }
        }

        .profile-role {
            color: var(--theme-green);
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 15px;
        }

        .profile-social {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .profile-social a {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark);
            font-size: 18px;
            transition: var(--transition);
            text-decoration: none;
        }

        .profile-social a:hover {
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
            transform: translateY(-3px);
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        @media (min-width: 480px) {
            .profile-stats {
                gap: 15px;
            }
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            display: block;
            font-size: 20px;
            font-weight: 700;
            color: var(--theme-blue);
            margin-bottom: 3px;
        }

        @media (min-width: 768px) {
            .stat-number {
                font-size: 24px;
            }
        }

        .stat-label {
            font-size: 11px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* About Content */
        .about-content {
            padding: 0;
        }

        @media (min-width: 992px) {
            .about-content {
                padding-right: 20px;
            }
        }

        .about-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 15px;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
        }

        .about-title {
            font-size: clamp(28px, 6vw, 42px);
            font-weight: 800;
            color: var(--theme-blue);
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .about-subtitle {
            font-size: 16px;
            color: var(--gray);
            margin-bottom: 25px;
            font-style: italic;
            line-height: 1.6;
        }

        @media (min-width: 768px) {
            .about-subtitle {
                font-size: 18px;
            }
        }

        .about-bio {
            margin-bottom: 30px;
        }

        .about-bio p {
            margin-bottom: 15px;
            color: <?php echo $about_text_color; ?>;
            font-size: var(--body-size);
            line-height: 1.8;
        }

        .about-bio .long-bio {
            white-space: pre-line;
        }

        /* Signature */
        .about-signature {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid var(--border);
        }

        .signature-message {
            font-size: 20px;
            font-weight: 400;
            color: var(--dark);
            margin-bottom: 8px;
            font-family: var(--heading-font);
            font-style: italic;
        }

        @media (min-width: 768px) {
            .signature-message {
                font-size: 24px;
            }
        }

        .signature-name {
            font-size: 24px;
            font-weight: 600;
            color: var(--theme-green);
            font-family: var(--heading-font);
        }

        @media (min-width: 768px) {
            .signature-name {
                font-size: 28px;
            }
        }

        /* CTA Section */
        <?php if ($about_cta_enabled == '1'): ?>
        .about-cta {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
            text-align: center;
            position: relative;
        }
        .about-cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.2);
            z-index: 1;
        }
        .cta-content { position: relative; z-index: 2; }

        .cta-content {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .cta-content h2 {
            font-size: clamp(28px, 6vw, 42px);
            font-weight: 800;
            margin-bottom: 15px;
            font-family: var(--heading-font);
        }

        .cta-content p {
            font-size: 16px;
            opacity: 0.95;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .cta-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            justify-content: center;
        }

        @media (min-width: 576px) {
            .cta-buttons {
                flex-direction: row;
                gap: 20px;
            }
        }

        .btn {
            padding: 14px 30px;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-block;
            text-align: center;
        }

        .btn-white {
            background: white;
            color: var(--theme-blue);
        }

        .btn-white:hover {
            background: #f0f6ff;
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.15);
        }

        .btn-outline-white {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.85);
        }

        .btn-outline-white:hover {
            background: white;
            color: var(--theme-blue);
            transform: translateY(-3px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.15);
        }
        <?php endif; ?>

        /* Explore Section */
        .about-explore {
            padding: 80px 0;
            background: linear-gradient(180deg, var(--light) 0%, #ffffff 100%);
        }
        .about-explore-header {
            text-align: center;
            max-width: 640px;
            margin: 0 auto 48px;
        }
        .about-explore-header h2 {
            font-family: var(--heading-font);
            font-size: clamp(28px, 5vw, 38px);
            color: var(--theme-blue);
            margin-bottom: 12px;
        }
        .about-explore-header p { color: var(--gray); font-size: 17px; line-height: 1.7; }
        .explore-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }
        @media (min-width: 768px) { .explore-grid { grid-template-columns: repeat(3, 1fr); } }
        .explore-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.9);
            border-radius: 24px;
            padding: 36px 28px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        .explore-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(37, 99, 235, 0.18);
            border-color: rgba(37, 99, 235, 0.25);
        }
        .explore-card-icon {
            width: 72px; height: 72px; margin: 0 auto 20px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.12) 0%, rgba(74, 124, 89, 0.08) 100%);
            border: 1px solid rgba(37, 99, 235, 0.2);
            font-size: 28px; color: var(--theme-blue);
            transition: var(--transition);
        }
        .explore-card:hover .explore-card-icon {
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
        }
        .explore-card h3 {
            font-family: var(--heading-font);
            font-size: 20px;
            color: var(--theme-blue);
            margin-bottom: 10px;
        }
        .explore-card p { color: var(--gray); font-size: 14px; line-height: 1.6; margin-bottom: 16px; }
        .explore-card-link {
            color: var(--theme-blue);
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .explore-card:hover .explore-card-link { gap: 12px; }

        /* Recent Content */
        .about-recent {
            padding: 0 0 80px;
            background: #ffffff;
        }
        .about-recent-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 40px;
        }
        @media (min-width: 992px) { .about-recent-grid { grid-template-columns: 1fr 1fr; } }
        .recent-block h3 {
            font-family: var(--heading-font);
            font-size: 22px;
            color: var(--theme-blue);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
            position: relative;
        }
        .recent-block h3::after {
            content: '';
            position: absolute;
            bottom: -2px; left: 0;
            width: 48px; height: 2px;
            background: linear-gradient(90deg, var(--theme-blue), var(--theme-green));
        }
        .recent-item {
            display: flex;
            gap: 16px;
            margin-bottom: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
            text-decoration: none;
            color: inherit;
            transition: var(--transition);
        }
        .recent-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .recent-item:hover { transform: translateX(4px); }
        .recent-item-thumb {
            width: 72px; height: 72px; border-radius: 14px;
            object-fit: cover; flex-shrink: 0;
            background: #eef4fb;
        }
        .recent-item-body h4 {
            font-size: 15px;
            font-weight: 600;
            color: var(--theme-blue);
            margin-bottom: 6px;
            line-height: 1.4;
        }
        .recent-item-body span { font-size: 12px; color: var(--gray); }
        .recent-view-all {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            color: var(--theme-green);
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
        }
        .recent-view-all:hover { gap: 12px; color: var(--theme-blue); }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading Skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: <?php echo $about_background_color; ?>;
        }

        ::-webkit-scrollbar-thumb {
            background: <?php echo $primary_color; ?>;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #2c4a3b;
        }

        /* Touch Optimizations */
        @media (hover: none) {
            .profile-card:hover {
                transform: none;
            }
            
            .btn:hover {
                transform: none;
            }
        }

        /* Print Styles */
        @media print {
            .modern-navbar, .mobile-drawer, .mobile-drawer-overlay,
            .footer,
            .about-cta,
            .btn {
                display: none;
            }
            
            body {
                padding: 0;
                background: white;
            }
            
            .about-hero {
                margin: 0;
                padding: 20px;
                color: black !important;
                background: white !important;
                min-height: auto;
            }
            
            .profile-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        <?php echo getSetting('custom_css', ''); ?>
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <header class="about-hero">
        <div class="container">
            <h1 data-aos="fade-up"><?php echo htmlspecialchars($about_title); ?></h1>
            <p data-aos="fade-up" data-aos-delay="100"><?php echo htmlspecialchars($about_subtitle); ?></p>
        </div>
    </header>

    <!-- Main About Section -->
    <section class="about-main">
        <div class="container">
            <div class="about-grid">
                <!-- Profile Card -->
                <div class="profile-card" data-aos="fade-right">
                    <div class="profile-image-wrapper">
                        <img src="<?php echo htmlspecialchars($about_profile_image); ?>" 
                             alt="<?php echo htmlspecialchars($about_name); ?>" 
                             class="profile-image"
                             loading="lazy">
                        <div class="profile-image-overlay"></div>
                    </div>
                    <div class="profile-info">
                        <h2 class="profile-name"><?php echo htmlspecialchars($about_name); ?></h2>
                        <p class="profile-role"><?php echo htmlspecialchars($about_role); ?></p>
                        
                        <div class="profile-social">
                            <?php if ($social_links['facebook'] && $social_links['facebook'] != '#'): ?>
                            <a href="<?php echo htmlspecialchars($social_links['facebook']); ?>" target="_blank" aria-label="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($social_links['instagram'] && $social_links['instagram'] != '#'): ?>
                            <a href="<?php echo htmlspecialchars($social_links['instagram']); ?>" target="_blank" aria-label="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($social_links['pinterest'] && $social_links['pinterest'] != '#'): ?>
                            <a href="<?php echo htmlspecialchars($social_links['pinterest']); ?>" target="_blank" aria-label="Pinterest">
                                <i class="fab fa-pinterest-p"></i>
                            </a>
                            <?php endif; ?>
                        </div>

                        <div class="profile-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $posts_count; ?></span>
                                <span class="stat-label">Posts</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $audio_count; ?></span>
                                <span class="stat-label">Audio</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?php echo $years_exp; ?>+</span>
                                <span class="stat-label">Years</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- About Content -->
                <div class="about-content" data-aos="fade-left">
                    <span class="about-badge">Welcome to my world</span>
                    <h1 class="about-title"><?php echo htmlspecialchars($about_title); ?></h1>
                    <p class="about-subtitle"><?php echo htmlspecialchars($about_subtitle); ?></p>
                    
                    <div class="about-bio">
                        <p><?php echo nl2br(htmlspecialchars($about_bio)); ?></p>
                        <div class="long-bio">
                            <?php echo nl2br(htmlspecialchars($about_long_bio)); ?>
                        </div>
                    </div>

                    <!-- Signature -->
                    <div class="about-signature">
                        <p class="signature-message"><?php echo htmlspecialchars($about_signature); ?></p>
                        <p class="signature-name"><?php echo htmlspecialchars($about_signature_name); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Explore Section -->
    <section class="about-explore">
        <div class="container">
            <div class="about-explore-header" data-aos="fade-up">
                <h2>What you'll find here</h2>
                <p>Honest writing, heartfelt audio, and a space to connect — all rooted in everyday faith and real life.</p>
            </div>
            <div class="explore-grid">
                <a href="blog.php" class="explore-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="explore-card-icon"><i class="fas fa-feather-pointed"></i></div>
                    <h3>Read the blog</h3>
                    <p>Stories, reflections, and experiences from daily life — written with honesty and heart.</p>
                    <span class="explore-card-link">Browse posts <i class="fas fa-arrow-right"></i></span>
                </a>
                <a href="audio.php" class="explore-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="explore-card-icon"><i class="fas fa-headphones"></i></div>
                    <h3>Listen to audio</h3>
                    <p>Words of hope and encouragement you can take with you wherever you go.</p>
                    <span class="explore-card-link">Start listening <i class="fas fa-arrow-right"></i></span>
                </a>
                <a href="contact.php" class="explore-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="explore-card-icon"><i class="fas fa-envelope"></i></div>
                    <h3>Get in touch</h3>
                    <p>Share your story, ask a question, or simply say hello — I'd love to hear from you.</p>
                    <span class="explore-card-link">Contact me <i class="fas fa-arrow-right"></i></span>
                </a>
            </div>
        </div>
    </section>

    <!-- Recent Content -->
    <?php if (!empty($recent_posts) || !empty($recent_audio)): ?>
    <section class="about-recent">
        <div class="container">
            <div class="about-recent-grid">
                <?php if (!empty($recent_posts)): ?>
                <div class="recent-block" data-aos="fade-up">
                    <h3><i class="fas fa-book-open"></i> Latest from the blog</h3>
                    <?php foreach ($recent_posts as $post): ?>
                    <a href="blog-post.php?slug=<?php echo urlencode($post['slug']); ?>" class="recent-item">
                        <img src="<?php echo !empty($post['featured_image']) ? 'uploads/images/' . htmlspecialchars($post['featured_image']) : 'assets/images/default-post.jpg'; ?>"
                             alt="" class="recent-item-thumb" loading="lazy">
                        <div class="recent-item-body">
                            <h4><?php echo htmlspecialchars(truncateText($post['title'], 55)); ?></h4>
                            <span><i class="far fa-calendar"></i> <?php echo date('M j, Y', strtotime($post['created_at'])); ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <a href="blog.php" class="recent-view-all">View all posts <i class="fas fa-arrow-right"></i></a>
                </div>
                <?php endif; ?>

                <?php if (!empty($recent_audio)): ?>
                <div class="recent-block" data-aos="fade-up" data-aos-delay="100">
                    <h3><i class="fas fa-headphones"></i> Recent audio</h3>
                    <?php foreach ($recent_audio as $audio): ?>
                    <a href="audio-player.php?id=<?php echo (int) $audio['id']; ?>" class="recent-item">
                        <img src="<?php
                            $cover = $audio['cover_image'] ?? '';
                            if (empty($cover)) echo 'assets/images/default-audio.jpg';
                            elseif (preg_match('/^https?:\/\//', $cover) || strpos($cover, 'uploads/') === 0 || strpos($cover, 'assets/') === 0) echo htmlspecialchars($cover);
                            else echo 'uploads/images/' . htmlspecialchars($cover);
                        ?>" alt="" class="recent-item-thumb" loading="lazy">
                        <div class="recent-item-body">
                            <h4><?php echo htmlspecialchars(truncateText($audio['title'], 55)); ?></h4>
                            <span><i class="far fa-clock"></i> <?php echo date('M j, Y', strtotime($audio['created_at'])); ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <a href="audio.php" class="recent-view-all">All audio messages <i class="fas fa-arrow-right"></i></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <?php if ($about_cta_enabled == '1'): ?>
    <section class="about-cta">
        <div class="container">
            <div class="cta-content" data-aos="fade-up">
                <h2>Let's Connect</h2>
                <p>I'd love to hear from you. Whether you have a question, a story to share, or just want to say hello.</p>
                <div class="cta-buttons">
                    <a href="contact.php" class="btn btn-white">Get in Touch</a>
                    <a href="audio.php" class="btn btn-outline-white">Listen to Audio</a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/main.js?v=20260904"></script>

    <script>
        // Initialize AOS with mobile optimization
        AOS.init({
            once: true,
            duration: window.innerWidth < 768 ? 400 : 800,
            easing: 'ease-in-out',
            disable: window.innerWidth < 576
        });

        // Theme toggle
        document.getElementById('themeToggle')?.addEventListener('click', function() {
            document.body.classList.toggle('dark-theme');
            const icon = this.querySelector('i');
            if (document.body.classList.contains('dark-theme')) {
                icon.className = 'fas fa-sun';
            } else {
                icon.className = 'fas fa-moon';
            }
            
            // Save preference to localStorage
            localStorage.setItem('darkTheme', document.body.classList.contains('dark-theme'));
        });

        // Check for saved theme preference
        if (localStorage.getItem('darkTheme') === 'true') {
            document.body.classList.add('dark-theme');
            const icon = document.querySelector('#themeToggle i');
            if (icon) icon.className = 'fas fa-sun';
        }

        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => imageObserver.observe(img));
        }

        // Add loading skeleton for profile image
        const profileImg = document.querySelector('.profile-image');
        if (profileImg && !profileImg.complete) {
            profileImg.classList.add('skeleton');
            profileImg.addEventListener('load', () => {
                profileImg.classList.remove('skeleton');
            });
            profileImg.addEventListener('error', () => {
                profileImg.classList.remove('skeleton');
            });
        }

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                AOS.refresh();
            }, 250);
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        <?php echo getSetting('custom_js', ''); ?>
    </script>
</body>
</html>