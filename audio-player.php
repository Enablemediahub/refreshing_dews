<?php
/**
 * Audio Page - Frontend
 * Display all audio messages with settings from admin
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db-connection.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get featured audio ID from settings
$featured_audio_id = getSetting('featured_audio_id', 0);

// Get all published audio messages with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = getSetting('audio_per_page', 9);
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Build query
$query = "SELECT a.*, u.username as author_name 
          FROM audio_messages a 
          LEFT JOIN users u ON a.author_id = u.id 
          WHERE a.status = 'published'";
$count_query = "SELECT COUNT(*) as total FROM audio_messages WHERE status = 'published'";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
    $count_query .= " AND (title LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$offset = ($page - 1) * $per_page;

// Get total count
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_audio = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_audio / $per_page);

// Get audio messages
$query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$per_page, $offset]));
} else {
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$audio_messages = [];
while ($row = $result->fetch_assoc()) {
    $audio_messages[] = $row;
}

// Get featured audio
$featured_audio = null;
$featured_audio_enabled = getSetting('audio_featured_enabled', '1');
if ($featured_audio_enabled == '1' && $page == 1 && empty($search) && empty($category)) {
    if ($featured_audio_id > 0) {
        // Get specific featured audio
        $stmt = $conn->prepare("SELECT a.*, u.username as author_name 
                                FROM audio_messages a 
                                LEFT JOIN users u ON a.author_id = u.id 
                                WHERE a.id = ? AND a.status = 'published'");
        $stmt->bind_param("i", $featured_audio_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $featured_audio = $result->fetch_assoc();
        }
    }
    
    // If no featured audio set, get the latest
    if (!$featured_audio && !empty($audio_messages)) {
        $featured_audio = $audio_messages[0];
    }
}

// Get settings
$site_title = getSetting('site_title', 'Painlesslyf');
$site_description = getSetting('site_description', 'Truth, grace, and the roadmap back to God\'s heart for your life and your marriage.');
$site_logo = getSetting('site_logo', 'assets/logo/painlesslyf-logo.png');
$favicon = getSetting('favicon', 'assets/logo/painlesslyf-logo.png');
$primary_color = getSetting('primary_color', '#C9A962');
$font_family = getSetting('font_family', 'Inter, sans-serif');
$enable_animated_bg = getSetting('enable_animated_background', '0');

// Get audio page specific settings
$audio_header_title = getSetting('audio_header_title', 'Audio Messages');
$audio_header_subtitle = getSetting('audio_header_subtitle', 'Heartfelt messages, Words of Hope from me to you.');
$audio_header_text_color = getSetting('audio_header_text_color', '#ffffff');
$audio_header_background_type = getSetting('audio_header_background_type', 'gradient');
$audio_header_background_solid = getSetting('audio_header_background_solid', '#1a2744');
$audio_header_background_gradient_start = getSetting('audio_header_background_gradient_start', '#1a2744');
$audio_header_background_gradient_end = getSetting('audio_header_background_gradient_end', '#0f1824');
$audio_header_background_image = getSetting('audio_header_background_image', '');
$audio_header_background_overlay = getSetting('audio_header_background_overlay', '0.6');

$audio_grid_title = getSetting('audio_grid_title', 'All Audio Messages');
$audio_grid_subtitle = getSetting('audio_grid_subtitle', 'Listen to messages on various topics - inspiration, teaching, reflections, and more.');

$audio_newsletter_enabled = getSetting('audio_newsletter_enabled', '1');
$audio_newsletter_title = getSetting('audio_newsletter_title', 'Never Miss a Message');
$audio_newsletter_subtitle = getSetting('audio_newsletter_subtitle', 'Subscribe to get notified when new audio messages are published.');

// Styling settings
$audio_background_color = getSetting('audio_background_color', '#f9fbf9');
$audio_card_background = getSetting('audio_card_background', '#ffffff');
$audio_card_shadow = getSetting('audio_card_shadow', '0 4px 6px rgba(0,0,0,0.05)');
$audio_card_hover_shadow = getSetting('audio_card_hover_shadow', '0 20px 40px rgba(0,0,0,0.15)');
$audio_title_color = getSetting('audio_title_color', '#1a2a1f');
$audio_text_color = getSetting('audio_text_color', '#6c757d');
$audio_meta_color = getSetting('audio_meta_color', '#6c757d');
$audio_category_background = getSetting('audio_category_background', '#C9A962');
$audio_category_color = getSetting('audio_category_color', '#ffffff');
$audio_duration_background = getSetting('audio_duration_background', 'rgba(0,0,0,0.7)');
$audio_duration_color = getSetting('audio_duration_color', '#ffffff');
$audio_button_color = getSetting('audio_button_color', '#C9A962');
$audio_button_hover_color = getSetting('audio_button_hover_color', '#2c4a3b');
$audio_play_button_background = getSetting('audio_play_button_background', '#1a2744');
$audio_play_button_hover_background = getSetting('audio_play_button_hover_background', '#2c4a3b');
$audio_play_button_color = getSetting('audio_play_button_color', '#ffffff');

// Mini player settings
$mini_player_background = getSetting('mini_player_background', '#ffffff');
$mini_player_text_color = getSetting('mini_player_text_color', '#333333');
$mini_player_button_background = getSetting('mini_player_button_background', '#1a2744');
$mini_player_button_color = getSetting('mini_player_button_color', '#ffffff');

// Typography settings
$audio_heading_font = getSetting('audio_heading_font', 'Playfair Display');
$audio_body_font = getSetting('audio_body_font', 'Inter');
$audio_heading_size = getSetting('audio_heading_size', '48');
$audio_body_size = getSetting('audio_body_size', '16');

// Get categories/topics for audio
$audio_categories = ['Inspiration', 'Reflections', 'Teaching', 'Stories', 'Meditation', 'Q&A'];

// Social media links
$social_links = [
    'instagram' => getSetting('instagram_url', '#'),
    'twitter' => getSetting('twitter_url', '#'),
    'youtube' => getSetting('youtube_url', '#'),
    'spotify' => getSetting('spotify_url', '#')
];

// Function to format duration
function formatDuration($duration) {
    if (empty($duration)) return '3:45';
    // If duration is in seconds format
    if (is_numeric($duration)) {
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        return sprintf("%d:%02d", $minutes, $seconds);
    }
    return $duration;
}

// Function to get audio header background style
function getAudioHeaderStyle() {
    global $audio_header_background_type, $audio_header_background_solid, 
           $audio_header_background_gradient_start, $audio_header_background_gradient_end,
           $audio_header_background_image, $audio_header_background_overlay;
    
    $style = "position: relative; overflow: hidden;";
    
    switch($audio_header_background_type) {
        case 'solid':
            $style .= " background-color: {$audio_header_background_solid};";
            break;
        case 'gradient':
            $style .= " background: linear-gradient(135deg, {$audio_header_background_gradient_start} 0%, {$audio_header_background_gradient_end} 100%);";
            break;
        case 'image':
            if (!empty($audio_header_background_image) && file_exists($audio_header_background_image)) {
                $style .= " background-image: url('{$audio_header_background_image}'); background-size: cover; background-position: center;";
                $style .= " position: relative;";
                $style .= " &::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0, {$audio_header_background_overlay}); z-index: 1; }";
            } else {
                $style .= " background: linear-gradient(135deg, {$audio_header_background_gradient_start} 0%, {$audio_header_background_gradient_end} 100%);";
            }
            break;
    }
    
    return $style;
}

// Helper function to get image URL
function getAudioImageUrl($path, $type = 'cover') {
    if (empty($path)) {
        return 'assets/images/default-audio.jpg';
    }
    // If it's a full path (starts with uploads/)
    if (strpos($path, 'uploads/') === 0) {
        return $path;
    }
    // If it's just filename (from old system)
    if ($type === 'cover') {
        return 'uploads/images/' . $path;
    }
    return $path;
}

// Get recent posts for footer
$recent_posts = getRecentPosts(3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($audio_header_title); ?> - <?php echo htmlspecialchars($site_title); ?></title>
    
    <!-- Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($audio_header_subtitle); ?>">
    <meta name="keywords" content="audio messages, podcast, reflections, teachings, inspiration, personal growth">
    <meta name="author" content="<?php echo htmlspecialchars($site_title); ?>">
    
    <!-- Open Graph Tags -->
    <meta property="og:title" content="<?php echo htmlspecialchars($audio_header_title); ?> - <?php echo htmlspecialchars($site_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($audio_header_subtitle); ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/<?php echo $site_logo; ?>">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/audio.php">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($audio_header_title); ?> - <?php echo htmlspecialchars($site_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($audio_header_subtitle); ?>">
    <meta name="twitter:image" content="<?php echo SITE_URL; ?>/<?php echo $site_logo; ?>">
    
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
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($audio_heading_font); ?>:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($audio_body_font); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <?php echo getPageHeroStyles(); ?>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* Modern Audio Page Styles - Fully Dynamic */
        :root {
            --primary: <?php echo $primary_color; ?>;
            --primary-dark: <?php echo $audio_button_hover_color; ?>;
            --primary-light: <?php echo $audio_play_button_background; ?>;
            --dark: <?php echo $audio_title_color; ?>;
            --light: <?php echo $audio_background_color; ?>;
            --gray: <?php echo $audio_text_color; ?>;
            --border: <?php echo $audio_meta_color; ?>;
            --shadow-sm: <?php echo $audio_card_shadow; ?>;
            --shadow-md: 0 10px 25px rgba(0, 0, 0, 0.1);
            --shadow-lg: <?php echo $audio_card_hover_shadow; ?>;
            --transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            --font-family: '<?php echo $audio_body_font; ?>', sans-serif;
            --heading-font: '<?php echo $audio_heading_font; ?>', serif;
            --heading-size: <?php echo $audio_heading_size; ?>px;
            --body-size: <?php echo $audio_body_size; ?>px;
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

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Audio Header — dimensions via getPageHeroStyles(); background set per page */
        .audio-header {
            color: <?php echo $audio_header_text_color; ?>;
            <?php echo getAudioHeaderStyle(); ?>
        }

        <?php if ($enable_animated_bg == '1'): ?>
        .audio-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.1"><circle cx="50" cy="50" r="40" fill="none" stroke="white" stroke-width="2"/><path d="M30 30 L70 70 M70 30 L30 70" stroke="white" stroke-width="2"/></svg>') repeat;
            background-size: 60px 60px;
            animation: float 20s linear infinite;
            z-index: 0;
            pointer-events: none;
        }

        @keyframes float {
            from { transform: translateY(0); }
            to { transform: translateY(-50px); }
        }
        <?php endif; ?>

        .audio-header h1 {
            animation: fadeInUp 1s ease;
            font-family: var(--heading-font);
        }

        .audio-header p {
            animation: fadeInUp 1s ease 0.2s both;
        }

        .audio-search {
            max-width: 500px;
            margin: 0 auto;
            position: relative;
            animation: fadeInUp 1s ease 0.4s both;
        }

        .audio-search input {
            width: 100%;
            padding: 15px 20px;
            padding-right: 50px;
            border: none;
            border-radius: 50px;
            font-size: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            transition: var(--transition);
        }

        .audio-search input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        .audio-search button {
            position: absolute;
            right: 5px;
            top: 5px;
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
        }

        .audio-search button:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        /* Category Filter */
        .category-filter {
            background: white;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            position: sticky;
            top: 70px;
            z-index: 99;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .filter-container {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            flex-wrap: nowrap;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .filter-container::-webkit-scrollbar {
            display: none;
        }

        .filter-btn {
            padding: 8px 20px;
            border: 2px solid rgba(0,0,0,0.1);
            background: white;
            color: var(--gray);
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            flex-shrink: 0;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        /* Featured Audio */
        .featured-audio {
            padding: 40px 0;
            background: white;
        }

        .featured-card {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        @media (min-width: 768px) {
            .featured-card {
                grid-template-columns: 1fr 1.2fr;
            }
        }

        .featured-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .featured-cover {
            height: 250px;
            overflow: hidden;
            position: relative;
        }

        @media (min-width: 768px) {
            .featured-cover {
                height: 400px;
            }
        }

        .featured-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .featured-card:hover .featured-cover img {
            transform: scale(1.05);
        }

        .featured-cover::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to right, rgba(0,0,0,0.2), transparent);
            z-index: 1;
        }

        .featured-play {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            background: var(--primary-light);
            border: 3px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            cursor: pointer;
            z-index: 2;
            transition: var(--transition);
            animation: pulse 2s infinite;
        }

        @media (min-width: 768px) {
            .featured-play {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.7); }
            70% { box-shadow: 0 0 0 15px rgba(255, 255, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); }
        }

        .featured-play:hover {
            background: var(--primary-dark);
            transform: translate(-50%, -50%) scale(1.1);
            animation: none;
        }

        .featured-content {
            padding: 25px;
        }

        @media (min-width: 768px) {
            .featured-content {
                padding: 40px 40px 40px 0;
            }
        }

        .featured-badge {
            background: var(--primary);
            color: white;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }

        .featured-content h2 {
            font-size: clamp(22px, 5vw, 32px);
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--dark);
            line-height: 1.3;
            font-family: var(--heading-font);
        }

        .featured-meta {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            color: var(--gray);
            font-size: 13px;
            flex-wrap: wrap;
        }

        .featured-meta i {
            color: var(--primary);
            margin-right: 3px;
        }

        .featured-description {
            font-size: 15px;
            color: var(--gray);
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .featured-player {
            background: #f8f9fa;
            border-radius: 60px;
            padding: 10px;
            margin-bottom: 20px;
        }

        .featured-player audio {
            width: 100%;
            height: 40px;
        }

        .featured-stats {
            display: flex;
            gap: 20px;
            color: var(--gray);
            font-size: 13px;
            flex-wrap: wrap;
        }

        .featured-stats i {
            color: var(--primary);
            margin-right: 5px;
        }

        /* Audio Grid */
        .audio-grid-section {
            padding: 40px 0;
            background: var(--light);
        }

        .grid-title {
            text-align: center;
            margin-bottom: 30px;
        }

        .grid-title h2 {
            font-size: clamp(24px, 6vw, 36px);
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
            font-family: var(--heading-font);
        }

        .grid-title p {
            font-size: 15px;
            color: var(--gray);
            max-width: 600px;
            margin: 0 auto;
            padding: 0 15px;
            line-height: 1.6;
        }

        .audio-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        @media (min-width: 576px) {
            .audio-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 992px) {
            .audio-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 25px;
            }
        }

        .audio-card {
            background: <?php echo $audio_card_background; ?>;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            animation: fadeInUp 0.8s ease both;
        }

        .audio-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .card-cover-wrapper {
            position: relative;
            height: 160px;
            overflow: hidden;
        }

        @media (min-width: 768px) {
            .card-cover-wrapper {
                height: 200px;
            }
        }

        .card-cover {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .audio-card:hover .card-cover {
            transform: scale(1.05);
        }

        .card-play-btn {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 45px;
            height: 45px;
            background: var(--primary-light);
            border: 2px solid white;
            border-radius: 50%;
            color: white;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: var(--transition);
            z-index: 2;
        }

        @media (min-width: 768px) {
            .card-play-btn {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }
        }

        .audio-card:hover .card-play-btn {
            opacity: 1;
        }

        .card-play-btn:hover {
            background: var(--primary-dark);
            transform: translate(-50%, -50%) scale(1.1);
        }

        .card-category {
            position: absolute;
            top: 10px;
            right: 10px;
            background: <?php echo $audio_category_background; ?>;
            color: <?php echo $audio_category_color; ?>;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            z-index: 2;
            backdrop-filter: blur(5px);
        }

        .card-duration {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: <?php echo $audio_duration_background; ?>;
            color: <?php echo $audio_duration_color; ?>;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            z-index: 2;
        }

        .card-content {
            padding: 15px;
        }

        @media (min-width: 768px) {
            .card-content {
                padding: 20px;
            }
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
            line-height: 1.4;
        }

        @media (min-width: 768px) {
            .card-title {
                font-size: 18px;
            }
        }

        .card-title a {
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
        }

        .card-title a:hover {
            color: var(--primary);
        }

        .card-description {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 12px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        .card-stats {
            display: flex;
            gap: 10px;
            color: var(--gray);
            font-size: 12px;
        }

        .card-stats i {
            color: var(--primary);
            margin-right: 2px;
        }

        .listen-link {
            color: <?php echo $audio_button_color; ?>;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .listen-link:hover {
            gap: 8px;
            color: <?php echo $audio_button_hover_color; ?>;
        }

        /* Mini Player Bar */
        .mini-player {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: <?php echo $mini_player_background; ?>;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.15);
            padding: 12px 0;
            z-index: 1000;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .mini-player.show {
            transform: translateY(0);
        }

        .mini-player .container {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        @media (min-width: 576px) {
            .mini-player .container {
                flex-wrap: nowrap;
            }
        }

        .mini-player-cover {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            object-fit: cover;
        }

        @media (min-width: 768px) {
            .mini-player-cover {
                width: 60px;
                height: 60px;
            }
        }

        .mini-player-info {
            flex: 1;
            min-width: 0;
        }

        .mini-player-title {
            font-weight: 600;
            color: <?php echo $mini_player_text_color; ?>;
            margin-bottom: 3px;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        @media (min-width: 768px) {
            .mini-player-title {
                font-size: 15px;
            }
        }

        .mini-player-author {
            font-size: 11px;
            color: var(--gray);
        }

        .mini-player-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: auto;
        }

        .mini-player-btn {
            width: 35px;
            height: 35px;
            border: none;
            background: <?php echo $mini_player_button_background; ?>;
            color: <?php echo $mini_player_button_color; ?>;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        @media (min-width: 768px) {
            .mini-player-btn {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }

        .mini-player-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .mini-player-close {
            background: none;
            border: none;
            color: var(--gray);
            font-size: 18px;
            cursor: pointer;
            padding: 5px;
            transition: var(--transition);
        }

        .mini-player-close:hover {
            color: var(--dark);
        }

        /* Pagination */
        .pagination-section {
            padding: 30px 0 50px;
            text-align: center;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            list-style: none;
            flex-wrap: wrap;
            padding: 0 15px;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            background: white;
            border: 2px solid rgba(0,0,0,0.1);
            border-radius: 10px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
        }

        @media (min-width: 768px) {
            .page-link {
                width: 45px;
                height: 45px;
                font-size: 15px;
            }
        }

        .page-link:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        .page-item.active .page-link {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Newsletter Section */
        <?php if ($audio_newsletter_enabled == '1'): ?>
        .newsletter-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 50px 0;
            color: white;
            text-align: center;
        }

        .newsletter-content {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .newsletter-content h2 {
            font-size: clamp(24px, 6vw, 36px);
            font-weight: 700;
            margin-bottom: 15px;
            font-family: var(--heading-font);
        }

        .newsletter-content p {
            font-size: 15px;
            opacity: 0.95;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .newsletter-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 500px;
            margin: 0 auto;
        }

        @media (min-width: 576px) {
            .newsletter-form {
                flex-direction: row;
            }
        }

        .newsletter-form input {
            flex: 1;
            padding: 14px 20px;
            border: none;
            border-radius: 50px;
            font-size: 15px;
            width: 100%;
        }

        .newsletter-form input:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
        }

        .newsletter-form button {
            padding: 14px 25px;
            background: white;
            color: var(--primary);
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
        }

        .newsletter-form button:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        <?php endif; ?>

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #4a7c59 0%, #2c4a3b 100%);
            color: white;
            padding: 40px 0 20px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (min-width: 576px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 992px) {
            .footer-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 30px;
            }
        }

        .footer-logo img {
            max-width: 120px;
            margin-bottom: 15px;
            background: white;
            padding: 8px;
            border-radius: 8px;
        }

        @media (min-width: 768px) {
            .footer-logo img {
                max-width: 150px;
            }
        }

        .footer-description {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .footer-social {
            display: flex;
            gap: 12px;
        }

        .footer-social a {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: var(--transition);
            font-size: 16px;
        }

        .footer-social a:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }

        .footer-col h3 {
            font-size: 16px;
            margin-bottom: 15px;
            position: relative;
            padding-bottom: 8px;
        }

        .footer-col h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 30px;
            height: 2px;
            background: var(--primary);
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .footer-links a:hover {
            color: white;
        }

        .footer-links i {
            font-size: 10px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.5);
            font-size: 13px;
        }

        /* No Audio Message */
        .no-audio {
            text-align: center;
            padding: 40px 15px;
            grid-column: 1 / -1;
        }

        .no-audio i {
            font-size: 60px;
            color: var(--gray);
            margin-bottom: 15px;
            opacity: 0.3;
        }

        .no-audio h3 {
            font-size: 20px;
            color: var(--dark);
            margin-bottom: 8px;
            font-family: var(--heading-font);
        }

        .no-audio p {
            color: var(--gray);
            font-size: 14px;
        }

        /* Animations */
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
            background: var(--light);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Touch Optimizations */
        @media (hover: none) {
            .audio-card:hover {
                transform: none;
            }
            
            .card-play-btn {
                opacity: 1;
                background: rgba(0,0,0,0.5);
            }
            
            .filter-btn:hover {
                transform: none;
            }
        }

        /* Print Styles */
        @media print {
            .modern-navbar, .mobile-drawer, .mobile-drawer-overlay,
            .category-filter,
            .audio-search,
            .mini-player,
            .newsletter-section,
            .footer {
                display: none;
            }
            
            body {
                padding: 0;
            }
            
            .audio-header {
                margin: 0;
                padding: 20px;
                color: black !important;
                background: white !important;
            }
        }

        <?php echo getSetting('custom_css', ''); ?>
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Audio Header -->
    <header class="audio-header">
        <div class="audio-header-content">
            <h1 data-aos="fade-up"><?php echo htmlspecialchars($audio_header_title); ?></h1>
            <p data-aos="fade-up" data-aos-delay="100"><?php echo htmlspecialchars($audio_header_subtitle); ?></p>
            <div class="audio-search" data-aos="fade-up" data-aos-delay="200">
                <form action="audio.php" method="GET">
                    <input type="text" name="search" placeholder="Search audio messages..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </header>

    <!-- Category Filter -->
    <div class="category-filter">
        <div class="filter-container">
            <button class="filter-btn active" data-category="all">All Audio</button>
            <?php foreach ($audio_categories as $category): ?>
            <button class="filter-btn" data-category="<?php echo strtolower($category); ?>"><?php echo $category; ?></button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Featured Audio -->
    <?php if ($featured_audio && $page == 1 && empty($search) && $featured_audio_enabled == '1'): ?>
    <section class="featured-audio">
        <div class="container">
            <div class="featured-card" data-aos="fade-up">
                <div class="featured-cover">
                    <img src="<?php echo getAudioImageUrl($featured_audio['cover_image'] ?? '', 'cover'); ?>" 
                         alt="<?php echo htmlspecialchars($featured_audio['title']); ?>">
                    <div class="featured-play" onclick="playAudio('<?php echo $featured_audio['id']; ?>', 'featured')">
                        <i class="fas fa-play"></i>
                    </div>
                </div>
                <div class="featured-content">
                    <span class="featured-badge">Featured Message</span>
                    <h2><?php echo htmlspecialchars($featured_audio['title']); ?></h2>
                    <div class="featured-meta">
                        <span><i class="far fa-calendar"></i> <?php echo date('F j, Y', strtotime($featured_audio['created_at'])); ?></span>
                        <span><i class="far fa-clock"></i> <?php echo formatDuration($featured_audio['duration']); ?></span>
                        <span><i class="far fa-user"></i> <?php echo htmlspecialchars($featured_audio['author_name'] ?? 'Admin'); ?></span>
                    </div>
                    <p class="featured-description"><?php echo htmlspecialchars($featured_audio['description']); ?></p>
                    
                    <div class="featured-player" id="featured-player-<?php echo $featured_audio['id']; ?>" style="display: none;">
                        <audio controls>
                            <source src="<?php echo htmlspecialchars($featured_audio['audio_file']); ?>" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
                    </div>
                    
                    <div class="featured-stats">
                        <span><i class="fas fa-headphones"></i> <?php echo number_format($featured_audio['plays']); ?> plays</span>
                        <span><i class="far fa-heart"></i> <?php echo rand(50, 500); ?> likes</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Audio Grid -->
    <section class="audio-grid-section">
        <div class="container">
            <div class="grid-title" data-aos="fade-up">
                <h2><?php echo htmlspecialchars($audio_grid_title); ?></h2>
                <p><?php echo htmlspecialchars($audio_grid_subtitle); ?></p>
            </div>

            <?php if (!empty($audio_messages)): ?>
            <div class="audio-grid">
                <?php 
                // Skip first audio if showing featured
                $display_audio = ($page == 1 && $featured_audio && $featured_audio_enabled == '1') ? array_slice($audio_messages, 1) : $audio_messages;
                foreach ($display_audio as $index => $audio): 
                ?>
                <div class="audio-card" data-aos="fade-up" data-aos-delay="<?php echo min($index * 50, 300); ?>">
                    <div class="card-cover-wrapper">
                        <img src="<?php echo getAudioImageUrl($audio['cover_image'] ?? '', 'cover'); ?>" 
                             alt="<?php echo htmlspecialchars($audio['title']); ?>" 
                             class="card-cover"
                             loading="lazy">
                        <div class="card-play-btn" onclick="playAudio('<?php echo $audio['id']; ?>', 'card')">
                            <i class="fas fa-play"></i>
                        </div>
                        <span class="card-category"><?php echo $audio_categories[array_rand($audio_categories)]; ?></span>
                        <span class="card-duration"><i class="far fa-clock"></i> <?php echo formatDuration($audio['duration']); ?></span>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">
                            <a href="audio-player.php?id=<?php echo $audio['id']; ?>"><?php echo htmlspecialchars($audio['title']); ?></a>
                        </h3>
                        <p class="card-description"><?php echo htmlspecialchars(substr($audio['description'], 0, 80)) . '...'; ?></p>
                        <div class="card-footer">
                            <div class="card-stats">
                                <span><i class="fas fa-headphones"></i> <?php echo number_format($audio['plays']); ?></span>
                                <span><i class="far fa-heart"></i> <?php echo rand(10, 100); ?></span>
                            </div>
                            <a href="audio-player.php?id=<?php echo $audio['id']; ?>" class="listen-link">
                                Listen <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Hidden audio player for inline play -->
                    <audio id="audio-<?php echo $audio['id']; ?>" preload="none" style="display: none;">
                        <source src="<?php echo htmlspecialchars($audio['audio_file']); ?>" type="audio/mpeg">
                    </audio>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-section">
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1' . (isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : '') . '">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="no-audio" data-aos="fade-up">
                <i class="fas fa-headphones"></i>
                <h3>No Audio Messages Yet</h3>
                <p>Check back soon for new audio messages and reflections.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Mini Player (hidden by default) -->
    <div class="mini-player" id="miniPlayer">
        <div class="container">
            <img src="" alt="Now Playing" class="mini-player-cover" id="miniPlayerCover">
            <div class="mini-player-info">
                <div class="mini-player-title" id="miniPlayerTitle"></div>
                <div class="mini-player-author" id="miniPlayerAuthor">Painlesslyf</div>
            </div>
            <div class="mini-player-controls">
                <button class="mini-player-btn" id="miniPlayerPlayPause">
                    <i class="fas fa-pause"></i>
                </button>
                <button class="mini-player-close" id="miniPlayerClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Newsletter Section -->
    <?php if ($audio_newsletter_enabled == '1'): ?>
    <section class="newsletter-section">
        <div class="newsletter-content" data-aos="fade-up">
            <h2><?php echo htmlspecialchars($audio_newsletter_title); ?></h2>
            <p><?php echo htmlspecialchars($audio_newsletter_subtitle); ?></p>
            <form class="newsletter-form" action="subscribe.php" method="POST">
                <input type="email" name="email" placeholder="Enter your email" required>
                <button type="submit">Subscribe</button>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/main.js"></script>

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
            localStorage.setItem('darkTheme', document.body.classList.contains('dark-theme'));
        });

        // Check for saved theme preference
        if (localStorage.getItem('darkTheme') === 'true') {
            document.body.classList.add('dark-theme');
            const icon = document.querySelector('#themeToggle i');
            if (icon) icon.className = 'fas fa-sun';
        }

        // Audio Player State
        let currentAudio = null;
        let currentPlayBtn = null;
        const miniPlayer = document.getElementById('miniPlayer');
        const miniPlayerCover = document.getElementById('miniPlayerCover');
        const miniPlayerTitle = document.getElementById('miniPlayerTitle');
        const miniPlayerAuthor = document.getElementById('miniPlayerAuthor');
        const miniPlayerPlayPause = document.getElementById('miniPlayerPlayPause');
        const miniPlayerClose = document.getElementById('miniPlayerClose');

        // Play audio function
        function playAudio(id, type) {
            const audio = document.getElementById('audio-' + id);
            const btn = event.currentTarget;
            const card = btn.closest('.audio-card');
            const featuredCard = btn.closest('.featured-card');
            
            if (!audio) return;
            
            // Pause currently playing audio
            if (currentAudio && currentAudio !== audio) {
                currentAudio.pause();
                if (currentPlayBtn) {
                    currentPlayBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
            }
            
            if (audio.paused) {
                audio.play();
                btn.innerHTML = '<i class="fas fa-pause"></i>';
                currentAudio = audio;
                currentPlayBtn = btn;
                
                // Show mini player
                if (card) {
                    const cover = card.querySelector('.card-cover').src;
                    const title = card.querySelector('.card-title').textContent;
                    miniPlayerCover.src = cover;
                    miniPlayerTitle.textContent = title;
                } else if (featuredCard) {
                    const cover = featuredCard.querySelector('.featured-cover img').src;
                    const title = featuredCard.querySelector('.featured-content h2').textContent;
                    miniPlayerCover.src = cover;
                    miniPlayerTitle.textContent = title;
                }
                
                miniPlayerAuthor.textContent = 'Painlesslyf';
                miniPlayer.classList.add('show');
                miniPlayerPlayPause.innerHTML = '<i class="fas fa-pause"></i>';
                
                // Handle audio end
                audio.addEventListener('ended', function() {
                    btn.innerHTML = '<i class="fas fa-play"></i>';
                    miniPlayer.classList.remove('show');
                    currentAudio = null;
                    currentPlayBtn = null;
                });
            } else {
                audio.pause();
                btn.innerHTML = '<i class="fas fa-play"></i>';
                miniPlayer.classList.remove('show');
                currentAudio = null;
                currentPlayBtn = null;
            }
        }

        // Mini player play/pause
        miniPlayerPlayPause.addEventListener('click', function() {
            if (currentAudio) {
                if (currentAudio.paused) {
                    currentAudio.play();
                    this.innerHTML = '<i class="fas fa-pause"></i>';
                    if (currentPlayBtn) {
                        currentPlayBtn.innerHTML = '<i class="fas fa-pause"></i>';
                    }
                } else {
                    currentAudio.pause();
                    this.innerHTML = '<i class="fas fa-play"></i>';
                    if (currentPlayBtn) {
                        currentPlayBtn.innerHTML = '<i class="fas fa-play"></i>';
                    }
                }
            }
        });

        // Close mini player
        miniPlayerClose.addEventListener('click', function() {
            if (currentAudio) {
                currentAudio.pause();
                if (currentPlayBtn) {
                    currentPlayBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
                miniPlayer.classList.remove('show');
                currentAudio = null;
                currentPlayBtn = null;
            }
        });

        // Category filter functionality
        const filterBtns = document.querySelectorAll('.filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const category = this.dataset.category;
                const cards = document.querySelectorAll('.audio-card');
                
                cards.forEach(card => {
                    if (category === 'all') {
                        card.style.display = 'block';
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'scale(1)';
                        }, 10);
                    } else {
                        card.style.display = 'block';
                    }
                });
            });
        });

        // Search functionality
        const searchForm = document.querySelector('.audio-search form');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const searchTerm = this.querySelector('input').value;
                if (searchTerm.trim()) {
                    window.location.href = 'audio.php?search=' + encodeURIComponent(searchTerm.trim());
                } else {
                    window.location.href = 'audio.php';
                }
            });
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

        // Add loading skeleton while images load
        document.querySelectorAll('.audio-card').forEach(card => {
            const img = card.querySelector('img');
            if (img && !img.complete) {
                card.classList.add('skeleton');
                img.addEventListener('load', () => {
                    card.classList.remove('skeleton');
                });
                img.addEventListener('error', () => {
                    card.classList.remove('skeleton');
                });
            }
        });

        // Handle touch events for mobile
        if ('ontouchstart' in window) {
            document.querySelectorAll('.card-play-btn, .featured-play').forEach(btn => {
                btn.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    this.click();
                });
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

        <?php echo getSetting('custom_js', ''); ?>
    </script>
</body>
</html>