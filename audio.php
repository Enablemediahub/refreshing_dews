<?php
/**
 * Audio Page - Frontend
 * Simplified audio player that works reliably on iOS
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
if ($featured_audio_enabled == '1' && $page == 1 && empty($search)) {
    if ($featured_audio_id > 0) {
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
    
    if (!$featured_audio && !empty($audio_messages)) {
        $featured_audio = $audio_messages[0];
    }
}

// Get settings
$site_title = getSetting('site_title', 'Painlesslyf');
$site_logo = getSetting('site_logo', 'assets/logo/painlesslyf-logo.png');
$favicon = getSetting('favicon', 'assets/logo/painlesslyf-logo.png');
$primary_color = getSetting('primary_color', '#C9A962');

// Theme colors (navy & gold — matches homepage and blog)
$theme_blue = getSetting('theme_blue_color', '#1a2744');
$theme_blue_dark = getSetting('theme_blue_dark_color', '#0f1824');
$theme_green = getSetting('theme_green_color', '#C9A962');
$theme_green_dark = getSetting('theme_green_dark_color', '#A68844');

// Get audio page specific settings
$audio_header_title = getSetting('audio_header_title', 'Audio Messages');
$audio_header_subtitle = getSetting('audio_header_subtitle', 'Heartfelt messages, Words of Hope from me to you.');
$audio_header_text_color = getSetting('audio_header_text_color', '#ffffff');
$audio_header_background_type = getSetting('audio_header_background_type', 'gradient');
$audio_header_background_solid = getSetting('audio_header_background_solid', $theme_green);
$audio_header_background_gradient_start = getSetting('audio_header_background_gradient_start', $theme_blue);
$audio_header_background_gradient_end = getSetting('audio_header_background_gradient_end', $theme_green);
$audio_header_background_image = getSetting('audio_header_background_image', '');
$audio_header_background_overlay = getSetting('audio_header_background_overlay', '0.6');

$audio_grid_title = getSetting('audio_grid_title', 'All Audio Messages');
$audio_grid_subtitle = getSetting('audio_grid_subtitle', 'Listen to messages on various topics');

$audio_newsletter_enabled = getSetting('audio_newsletter_enabled', '1');
$audio_newsletter_title = getSetting('audio_newsletter_title', 'Never Miss a Message');
$audio_newsletter_subtitle = getSetting('audio_newsletter_subtitle', 'Subscribe to get notified when new audio messages are published.');

// Styling settings
$audio_background_color = getSetting('audio_background_color', '#faf7f0');
$audio_card_background = getSetting('audio_card_background', '#ffffff');
$audio_title_color = getSetting('audio_title_color', '#1e4d72');
$audio_text_color = getSetting('audio_text_color', '#6b6b6b');
$audio_meta_color = getSetting('audio_meta_color', '#6b6b6b');
$audio_category_background = getSetting('audio_category_background', $theme_green);
$audio_category_color = getSetting('audio_category_color', '#ffffff');
$audio_duration_background = getSetting('audio_duration_background', 'rgba(30, 77, 114, 0.85)');
$audio_duration_color = getSetting('audio_duration_color', '#ffffff');
$audio_button_color = getSetting('audio_button_color', $theme_blue);
$audio_button_hover_color = getSetting('audio_button_hover_color', $theme_blue_dark);
$audio_play_button_background = getSetting('audio_play_button_background', $theme_blue);
$audio_play_button_hover_background = getSetting('audio_play_button_hover_background', $theme_green);
$audio_play_button_color = getSetting('audio_play_button_color', '#ffffff');
$audio_heading_font = getSetting('audio_heading_font', 'Playfair Display');
$audio_body_font = getSetting('audio_body_font', 'Inter');
$audio_heading_size = getSetting('audio_heading_size', '48');
$audio_body_size = getSetting('audio_body_size', '16');
$audio_card_shadow = getSetting('audio_card_shadow', '0 4px 20px rgba(37, 99, 235, 0.08)');
$audio_card_hover_shadow = getSetting('audio_card_hover_shadow', '0 20px 40px rgba(37, 99, 235, 0.22)');

// Social media links - Only Facebook, Instagram, Pinterest
$social_links = [
    'facebook' => getSetting('facebook_url', '#'),
    'instagram' => getSetting('instagram_url', '#'),
    'pinterest' => getSetting('pinterest_url', '#')
];

// Function to format duration
function formatDuration($duration) {
    if (empty($duration)) return '--:--';
    if (is_numeric($duration)) {
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        return sprintf("%d:%02d", $minutes, $seconds);
    }
    return $duration;
}

// Function to get audio file URL
function getAudioFileUrl($audio_file) {
    if (empty($audio_file)) {
        return '';
    }
    
    // If it's already a full URL
    if (preg_match('/^https?:\/\//', $audio_file)) {
        return $audio_file;
    }
    
    // Remove any leading slashes
    $audio_file = ltrim($audio_file, '/');
    
    // If it starts with uploads/
    if (strpos($audio_file, 'uploads/') === 0) {
        return $audio_file;
    }
    
    // Otherwise assume it's just a filename in uploads/audio/
    return 'uploads/audio/' . $audio_file;
}

// Function to get cover image URL
function getCoverImageUrl($cover_image) {
    if (empty($cover_image)) {
        return 'assets/images/default-audio.jpg';
    }
    
    if (preg_match('/^https?:\/\//', $cover_image)) {
        return $cover_image;
    }
    
    $cover_image = ltrim($cover_image, '/');
    
    if (strpos($cover_image, 'uploads/') === 0 || strpos($cover_image, 'assets/') === 0) {
        return $cover_image;
    }
    
    return 'uploads/images/' . $cover_image;
}

// Function to get audio header background style
function getAudioHeaderStyle() {
    global $audio_header_background_type, $audio_header_background_solid, 
           $audio_header_background_gradient_start, $audio_header_background_gradient_end,
           $audio_header_background_image, $audio_header_background_overlay;
    
    switch($audio_header_background_type) {
        case 'solid':
            return "background-color: {$audio_header_background_solid};";
        case 'gradient':
            return "background: linear-gradient(135deg, {$audio_header_background_gradient_start} 0%, {$audio_header_background_gradient_end} 100%);";
        case 'image':
            if (!empty($audio_header_background_image)) {
                $overlay = is_numeric($audio_header_background_overlay) ? max(0, min(1, (float) $audio_header_background_overlay)) : 0.6;
                return "background: linear-gradient(rgba(0,0,0,{$overlay}), rgba(0,0,0,{$overlay})), url('{$audio_header_background_image}'); background-size: cover; background-position: center;";
            }
            return "background: linear-gradient(135deg, {$audio_header_background_gradient_start} 0%, {$audio_header_background_gradient_end} 100%);";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($audio_header_title); ?> - <?php echo htmlspecialchars($site_title); ?></title>
    
    <meta name="description" content="<?php echo htmlspecialchars($audio_header_subtitle); ?>">
    <meta name="keywords" content="audio messages, podcast, reflections, teachings">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $favicon; ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $favicon; ?>">
    <link rel="apple-touch-icon" href="<?php echo $site_logo; ?>">
    
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
            --primary: <?php echo $audio_button_color; ?>;
            --primary-dark: <?php echo $audio_button_hover_color; ?>;
            --title-color: <?php echo $audio_title_color; ?>;
            --body-text: <?php echo $audio_text_color; ?>;
            --meta-text: <?php echo $audio_meta_color; ?>;
            --light: <?php echo $audio_background_color; ?>;
            --card-bg: <?php echo $audio_card_background; ?>;
            --heading-font: '<?php echo addslashes($audio_heading_font); ?>', serif;
            --body-font: '<?php echo addslashes($audio_body_font); ?>', sans-serif;
            --heading-size: <?php echo (int) $audio_heading_size; ?>px;
            --body-size: <?php echo (int) $audio_body_size; ?>px;
            --shadow-sm: 0 4px 20px rgba(37, 99, 235, 0.08);
            --shadow-md: 0 8px 30px rgba(37, 99, 235, 0.12);
            --shadow-lg: 0 20px 50px rgba(37, 99, 235, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--body-font); font-size: var(--body-size); color: var(--body-text); background: var(--light); overflow-x: hidden; }
        .container { width: 100%; max-width: 1280px; margin: 0 auto; padding: 0 24px; }
        
        /* Audio Header — dimensions via getPageHeroStyles(); background set per page */
        .audio-header {
            color: <?php echo $audio_header_text_color; ?>;
            <?php echo getAudioHeaderStyle(); ?>
            text-align: center;
        }
        .audio-header h1 { animation: fadeInUp 1s ease; }
        .audio-header p { animation: fadeInUp 1s ease 0.2s both; }
        .audio-search { max-width: 500px; margin: 0 auto; position: relative; }
        .audio-search input { width: 100%; padding: 16px 22px; padding-right: 56px; border: none; border-radius: 50px; font-size: 15px; box-shadow: var(--shadow-lg); }
        .audio-search button {
            position: absolute; right: 6px; top: 6px;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white; border: none; width: 44px; height: 44px; border-radius: 50%; cursor: pointer;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
            transition: var(--transition);
        }
        .audio-search button:hover { transform: scale(1.08); }
        
        /* Featured Audio */
        .featured-audio { padding: 60px 0 20px; background: transparent; }
        .featured-card {
            display: grid; grid-template-columns: 1fr; gap: 30px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 28px; overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.9);
            transition: var(--transition);
        }
        .featured-card:hover { transform: translateY(-8px); box-shadow: 0 24px 60px rgba(37, 99, 235, 0.18); border-color: rgba(37, 99, 235, 0.25); }
        @media (min-width: 768px) { .featured-card { grid-template-columns: 0.85fr 1.15fr; } }
        .featured-cover { height: 280px; position: relative; overflow: hidden; }
        @media (min-width: 768px) { .featured-cover { height: auto; min-height: 380px; } }
        .featured-cover img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .featured-card:hover .featured-cover img { transform: scale(1.05); }
        .featured-play {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 72px; height: 72px;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            border: 3px solid white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 28px; cursor: pointer; transition: var(--transition);
            box-shadow: 0 8px 28px rgba(37, 99, 235, 0.4);
        }
        .featured-play:hover { transform: translate(-50%, -50%) scale(1.08); }
        .featured-content { padding: 30px; display: flex; flex-direction: column; justify-content: center; }
        .featured-badge {
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white; padding: 8px 18px; border-radius: 30px; font-size: 12px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 6px; margin-bottom: 15px; width: fit-content;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
        }
        .featured-content h2 { font-size: 28px; font-weight: 700; margin-bottom: 15px; font-family: var(--heading-font); color: var(--theme-blue); line-height: 1.3; }
        .featured-meta { display: flex; gap: 20px; color: var(--meta-text); font-size: 13px; margin-bottom: 15px; flex-wrap: wrap; }
        .featured-meta i { color: var(--theme-green); margin-right: 4px; }
        .featured-description { color: var(--body-text); margin-bottom: 20px; line-height: 1.7; }
        
        /* Audio Grid */
        .audio-grid-section { padding: 60px 0 80px; }
        .grid-title { text-align: center; margin-bottom: 40px; }
        .grid-title h2 { font-size: 36px; font-weight: 700; font-family: var(--heading-font); margin-bottom: 12px; color: var(--theme-blue); }
        .grid-title p { color: var(--body-text); max-width: 600px; margin: 0 auto; }
        .audio-grid { display: grid; grid-template-columns: 1fr; gap: 28px; }
        @media (min-width: 576px) { .audio-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 992px) { .audio-grid { grid-template-columns: repeat(3, 1fr); } }
        .audio-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
            border-radius: 20px; overflow: hidden;
            box-shadow: <?php echo $audio_card_shadow; ?>;
            border: 1px solid rgba(255, 255, 255, 0.85);
            transition: var(--transition);
        }
        .audio-card:hover {
            transform: translateY(-8px);
            box-shadow: <?php echo $audio_card_hover_shadow; ?>;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            border-color: transparent;
        }
        .audio-card:hover .card-title a,
        .audio-card:hover .card-description,
        .audio-card:hover .listen-link,
        .audio-card:hover .card-stats { color: rgba(255, 255, 255, 0.92) !important; }
        .audio-card:hover .card-footer { border-top-color: rgba(255, 255, 255, 0.2); }
        .card-cover-wrapper { position: relative; height: 200px; overflow: hidden; background: #eef4fb; }
        .card-cover { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .audio-card:hover .card-cover { transform: scale(1.06); }
        .card-play-btn {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 52px; height: 52px;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            border: 2px solid white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 18px; cursor: pointer; transition: var(--transition);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
        }
        .card-play-btn:hover { transform: translate(-50%, -50%) scale(1.1); }
        .card-duration { position: absolute; bottom: 12px; left: 12px; background: <?php echo $audio_duration_background; ?>; color: <?php echo $audio_duration_color; ?>; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .card-content { padding: 20px; }
        .card-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; font-family: var(--heading-font); }
        .card-title a { color: var(--theme-blue); text-decoration: none; transition: var(--transition); }
        .card-description { font-size: 13px; color: var(--body-text); margin-bottom: 12px; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .card-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 12px; border-top: 1px solid rgba(37, 99, 235, 0.1); }
        .listen-link { color: var(--theme-blue); text-decoration: none; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 5px; transition: var(--transition); }
        .listen-link:hover { gap: 8px; }
        
        /* Audio Player Modal - SIMPLE FIX */
        .audio-player-modal {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -5px 30px rgba(0,0,0,0.2);
            z-index: 1000;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            padding: 20px;
        }
        .audio-player-modal.show {
            transform: translateY(0);
        }
        .audio-player-inner {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .audio-player-cover {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
        }
        .audio-player-info {
            flex: 1;
            min-width: 0;
        }
        .audio-player-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--title-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .audio-player-author {
            font-size: 12px;
            color: var(--body-text);
        }
        .audio-player-controls {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .audio-player-btn {
            width: 45px; height: 45px; border: none;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white; border-radius: 50%; cursor: pointer; font-size: 18px; transition: var(--transition);
        }
        .audio-player-btn:hover { transform: scale(1.05); }
        .audio-player-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--body-text);
            padding: 10px;
        }
        
        /* Pagination */
        .pagination-section { padding: 40px 0; text-align: center; }
        .pagination { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; list-style: none; }
        .page-link {
            display: flex; align-items: center; justify-content: center; min-width: 42px; height: 42px;
            background: white; border: 2px solid rgba(37, 99, 235, 0.15); border-radius: 12px;
            color: var(--theme-blue); text-decoration: none; font-weight: 600; transition: var(--transition);
        }
        .page-link:hover, .page-item.active .page-link {
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            border-color: transparent; color: white;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
        }
        
        /* Newsletter */
        <?php if ($audio_newsletter_enabled == '1'): ?>
        .newsletter-section {
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            padding: 80px 0; color: white; text-align: center; position: relative;
        }
        .newsletter-section::before {
            content: ''; position: absolute; inset: 0; background: rgba(0, 0, 0, 0.2); z-index: 1;
        }
        .newsletter-content { max-width: 550px; margin: 0 auto; position: relative; z-index: 2; padding: 0 20px; }
        .newsletter-content h2 { font-size: 32px; margin-bottom: 15px; font-family: var(--heading-font); }
        .newsletter-form { display: flex; flex-direction: column; gap: 10px; margin-top: 25px; }
        @media (min-width: 576px) { .newsletter-form { flex-direction: row; } }
        .newsletter-form input { flex: 1; padding: 14px 20px; border: none; border-radius: 50px; }
        .newsletter-form button { padding: 14px 30px; background: white; color: var(--theme-blue); border: none; border-radius: 50px; font-weight: 600; cursor: pointer; transition: var(--transition); }
        .newsletter-form button:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
        <?php endif; ?>
        
        .no-audio { text-align: center; padding: 60px 20px; background: white; border-radius: 20px; box-shadow: var(--shadow-sm); }
        .no-audio i { font-size: 60px; color: var(--theme-blue); margin-bottom: 20px; opacity: 0.45; display: block; }
        .no-audio h3 { color: var(--theme-blue); margin-bottom: 10px; font-family: var(--heading-font); }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @media (max-width: 768px) {
            .grid-title h2 { font-size: 28px; }
            .featured-content h2 { font-size: 24px; }
            .audio-player-inner { flex-wrap: wrap; justify-content: center; text-align: center; }
            .audio-player-info { text-align: center; }
        }
        
        <?php echo getSetting('custom_css', ''); ?>
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Audio Header -->
    <header class="audio-header">
        <div class="container">
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

    <!-- Featured Audio -->
    <?php if ($featured_audio && $page == 1 && empty($search) && $featured_audio_enabled == '1'): ?>
    <section class="featured-audio">
        <div class="container">
            <div class="featured-card" data-aos="fade-up">
                <div class="featured-cover">
                    <img src="<?php echo getCoverImageUrl($featured_audio['cover_image'] ?? ''); ?>" 
                         alt="<?php echo htmlspecialchars($featured_audio['title']); ?>">
                    <button class="featured-play play-btn" 
                            data-id="<?php echo (int) $featured_audio['id']; ?>"
                            data-src="<?php echo htmlspecialchars(getAudioFileUrl($featured_audio['audio_file'])); ?>"
                            data-title="<?php echo htmlspecialchars($featured_audio['title']); ?>"
                            data-cover="<?php echo getCoverImageUrl($featured_audio['cover_image'] ?? ''); ?>">
                        <i class="fas fa-play"></i>
                    </button>
                </div>
                <div class="featured-content">
                    <span class="featured-badge">✨ Featured Message</span>
                    <h2><?php echo htmlspecialchars($featured_audio['title']); ?></h2>
                    <div class="featured-meta">
                        <span><i class="far fa-calendar"></i> <?php echo date('F j, Y', strtotime($featured_audio['created_at'])); ?></span>
                        <span><i class="far fa-clock"></i> <?php echo formatDuration($featured_audio['duration']); ?></span>
                        <span><i class="fas fa-headphones"></i> <span class="play-count" data-audio-id="<?php echo (int) $featured_audio['id']; ?>"><?php echo number_format($featured_audio['plays']); ?></span> plays</span>
                    </div>
                    <p class="featured-description"><?php echo htmlspecialchars($featured_audio['description']); ?></p>
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
                $display_audio = ($page == 1 && $featured_audio && $featured_audio_enabled == '1') ? array_slice($audio_messages, 1) : $audio_messages;
                foreach ($display_audio as $index => $audio): 
                ?>
                <div class="audio-card" data-aos="fade-up" data-aos-delay="<?php echo min($index * 50, 300); ?>">
                    <div class="card-cover-wrapper">
                        <img src="<?php echo getCoverImageUrl($audio['cover_image'] ?? ''); ?>" 
                             alt="<?php echo htmlspecialchars($audio['title']); ?>" 
                             class="card-cover">
                        <button class="card-play-btn play-btn"
                                data-id="<?php echo (int) $audio['id']; ?>"
                                data-src="<?php echo htmlspecialchars(getAudioFileUrl($audio['audio_file'])); ?>"
                                data-title="<?php echo htmlspecialchars($audio['title']); ?>"
                                data-cover="<?php echo getCoverImageUrl($audio['cover_image'] ?? ''); ?>">
                            <i class="fas fa-play"></i>
                        </button>
                        <span class="card-duration"><i class="far fa-clock"></i> <?php echo formatDuration($audio['duration']); ?></span>
                    </div>
                    <div class="card-content">
                        <h3 class="card-title">
                            <a href="audio-player.php?id=<?php echo $audio['id']; ?>"><?php echo htmlspecialchars($audio['title']); ?></a>
                        </h3>
                        <p class="card-description"><?php echo htmlspecialchars(substr($audio['description'], 0, 100)); ?>...</p>
                        <div class="card-footer">
                            <span class="card-stats"><i class="fas fa-headphones"></i> <span class="play-count" data-audio-id="<?php echo (int) $audio['id']; ?>"><?php echo number_format($audio['plays']); ?></span></span>
                            <a href="audio-player.php?id=<?php echo $audio['id']; ?>" class="listen-link">
                                Listen <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-section">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="no-audio">
                <i class="fas fa-headphones"></i>
                <h3>No Audio Messages Yet</h3>
                <p>Check back soon for new audio messages.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Simple Audio Player Modal -->
    <div class="audio-player-modal" id="audioPlayerModal">
        <div class="container">
            <div class="audio-player-inner">
                <img src="" alt="Cover" class="audio-player-cover" id="playerCover">
                <div class="audio-player-info">
                    <div class="audio-player-title" id="playerTitle">Select an audio</div>
                    <div class="audio-player-author">Painlesslyf</div>
                </div>
                <div class="audio-player-controls">
                    <button class="audio-player-btn" id="playerPlayPause">
                        <i class="fas fa-play"></i>
                    </button>
                    <button class="audio-player-close" id="playerClose">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
        <audio id="playerAudio" style="display: none;"></audio>
    </div>

    <!-- Newsletter Section -->
    <?php if ($audio_newsletter_enabled == '1'): ?>
    <section class="newsletter-section">
        <div class="container">
            <div class="newsletter-content">
                <h2><?php echo htmlspecialchars($audio_newsletter_title); ?></h2>
                <p><?php echo htmlspecialchars($audio_newsletter_subtitle); ?></p>
                <form class="newsletter-form" action="subscribe.php" method="POST">
                    <input type="email" name="email" placeholder="Enter your email" required>
                    <button type="submit">Subscribe</button>
                </form>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, duration: 800 });
        
        // ============ SIMPLE AUDIO PLAYER - WORKS ON iOS ============
        const modal = document.getElementById('audioPlayerModal');
        const playerAudio = document.getElementById('playerAudio');
        const playerCover = document.getElementById('playerCover');
        const playerTitle = document.getElementById('playerTitle');
        const playerPlayPause = document.getElementById('playerPlayPause');
        const playerClose = document.getElementById('playerClose');
        
        let currentPlayingBtn = null;
        const trackedPlayIds = new Set();

        function updateDisplayedPlayCount(audioId, plays) {
            document.querySelectorAll(`.play-count[data-audio-id="${audioId}"]`).forEach(el => {
                el.textContent = Number(plays).toLocaleString();
            });
        }

        async function incrementPlayCount(audioId) {
            const normalizedId = String(audioId || '').trim();
            if (!normalizedId || trackedPlayIds.has(normalizedId)) {
                return;
            }

            trackedPlayIds.add(normalizedId);

            try {
                const response = await fetch('increment-audio-play.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `audio_id=${encodeURIComponent(normalizedId)}`
                });

                const data = await response.json();
                if (response.ok && data.success && typeof data.plays !== 'undefined') {
                    updateDisplayedPlayCount(normalizedId, data.plays);
                } else {
                    trackedPlayIds.delete(normalizedId);
                }
            } catch (error) {
                console.error('Play count update failed:', error);
                trackedPlayIds.delete(normalizedId);
            }
        }
        
        // Play audio function
        function playAudio(src, title, cover, button, audioId) {
            // If same audio is playing, pause it
            if (currentPlayingBtn === button && !playerAudio.paused) {
                playerAudio.pause();
                playerPlayPause.innerHTML = '<i class="fas fa-play"></i>';
                if (button) {
                    const icon = button.querySelector('i');
                    if (icon) icon.className = 'fas fa-play';
                }
                modal.classList.remove('show');
                currentPlayingBtn = null;
                return;
            }
            
            // Reset previous button icon
            if (currentPlayingBtn && currentPlayingBtn !== button) {
                const prevIcon = currentPlayingBtn.querySelector('i');
                if (prevIcon) prevIcon.className = 'fas fa-play';
            }
            
            // Set new audio
            playerAudio.src = src;
            playerCover.src = cover;
            playerTitle.textContent = title;
            
            // Update button icon
            const playIcon = button.querySelector('i');
            if (playIcon) playIcon.className = 'fas fa-pause';
            
            // Load and play
            playerAudio.load();
            
            // iOS requires user interaction - play in promise
            const playPromise = playerAudio.play();
            if (playPromise !== undefined) {
                playPromise.then(() => {
                    playerPlayPause.innerHTML = '<i class="fas fa-pause"></i>';
                    modal.classList.add('show');
                    currentPlayingBtn = button;
                    incrementPlayCount(audioId);
                }).catch(error => {
                    console.log('Playback error:', error);
                    if (playIcon) playIcon.className = 'fas fa-play';
                    modal.classList.add('show');
                    currentPlayingBtn = button;
                    playerPlayPause.innerHTML = '<i class="fas fa-play"></i>';
                });
            }
            
            // Handle audio end
            playerAudio.onended = function() {
                playerPlayPause.innerHTML = '<i class="fas fa-play"></i>';
                if (currentPlayingBtn) {
                    const icon = currentPlayingBtn.querySelector('i');
                    if (icon) icon.className = 'fas fa-play';
                }
                modal.classList.remove('show');
                currentPlayingBtn = null;
            };
            
            // Handle errors
            playerAudio.onerror = function(e) {
                console.error('Audio error:', e);
                alert('Unable to play audio. The file may be corrupted or in an unsupported format.');
                if (currentPlayingBtn) {
                    const icon = currentPlayingBtn.querySelector('i');
                    if (icon) icon.className = 'fas fa-play';
                }
                modal.classList.remove('show');
                currentPlayingBtn = null;
            };
        }
        
        // Add click handlers to all play buttons
        document.querySelectorAll('.play-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const audioId = this.getAttribute('data-id');
                const src = this.getAttribute('data-src');
                const title = this.getAttribute('data-title');
                const cover = this.getAttribute('data-cover');
                
                if (src && title) {
                    playAudio(src, title, cover, this, audioId);
                } else {
                    console.error('Missing audio data');
                    alert('Audio file not found.');
                }
            });
        });
        
        // Player controls
        playerPlayPause.addEventListener('click', function() {
            if (playerAudio.paused) {
                playerAudio.play();
                this.innerHTML = '<i class="fas fa-pause"></i>';
                if (currentPlayingBtn) {
                    const icon = currentPlayingBtn.querySelector('i');
                    if (icon) icon.className = 'fas fa-pause';
                }
            } else {
                playerAudio.pause();
                this.innerHTML = '<i class="fas fa-play"></i>';
                if (currentPlayingBtn) {
                    const icon = currentPlayingBtn.querySelector('i');
                    if (icon) icon.className = 'fas fa-play';
                }
            }
        });
        
        playerClose.addEventListener('click', function() {
            playerAudio.pause();
            playerAudio.currentTime = 0;
            playerPlayPause.innerHTML = '<i class="fas fa-play"></i>';
            if (currentPlayingBtn) {
                const icon = currentPlayingBtn.querySelector('i');
                if (icon) icon.className = 'fas fa-play';
            }
            modal.classList.remove('show');
            currentPlayingBtn = null;
        });
        
        // Close modal when clicking outside on mobile (optional)
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.audio-player-modal') && !e.target.closest('.play-btn') && modal.classList.contains('show')) {
                playerAudio.pause();
                modal.classList.remove('show');
                if (currentPlayingBtn) {
                    const icon = currentPlayingBtn.querySelector('i');
                    if (icon) icon.className = 'fas fa-play';
                }
                currentPlayingBtn = null;
            }
        });
        
        // iOS: Ensure audio plays on user interaction
        if ('ontouchstart' in window) {
            document.body.addEventListener('touchstart', function() {
                // Just initializes audio context - no actual play
                console.log('iOS touch detected');
            }, { once: true });
        }
        
        // Auto-hide alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(a => a.style.display = 'none');
        }, 5000);
        
        <?php echo getSetting('custom_js', ''); ?>
    </script>
</body>
</html>
