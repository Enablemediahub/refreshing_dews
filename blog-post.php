<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db-connection.php';

// Fallback function in case includes/functions.php didn't load getSeoImageUrl
if (!function_exists('getSeoImageUrl')) {
    function getSeoImageUrl($imagePath = '', $fallback = 'assets/logo/painlesslyf-logo.png') {
        $path = trim((string) $imagePath);
        if ($path === '') {
            $path = $fallback;
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        if (strpos($path, 'assets/') === 0 || strpos($path, 'uploads/') === 0 || strpos($path, 'admin/') === 0) {
            return (defined('SITE_URL') ? SITE_URL : '') . '/' . $path;
        }
        return (defined('SITE_URL') ? SITE_URL : '') . '/uploads/images/' . ltrim($path, '/');
    }
}

// Fallback function in case includes/functions.php didn't load renderSeoMetaTags
if (!function_exists('renderSeoMetaTags')) {
    function renderSeoMetaTags($options = []) {
        $title = $options['title'] ?? 'Painlesslyf';
        $description = $options['description'] ?? 'Truth, grace, and the roadmap back to God\'s heart for your life and your marriage.';
        $canonical = $options['canonical'] ?? '';
        $image = $options['image'] ?? '';
        $type = $options['type'] ?? 'website';
        $author = $options['author'] ?? 'Painlesslyf';
        $keywords = $options['keywords'] ?? '';

        $html = [];
        $html[] = '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        $html[] = '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<meta name="robots" content="index, follow">';
        if ($canonical) {
            $html[] = '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html[] = '<meta name="author" content="' . htmlspecialchars($author, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<meta property="og:site_name" content="Painlesslyf">';
        $html[] = '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">';
        if ($canonical) {
            $html[] = '<meta property="og:url" content="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html[] = '<meta property="og:type" content="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">';
        if ($image) {
            $html[] = '<meta property="og:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">';
        }
        $html[] = '<meta name="twitter:card" content="summary_large_image">';
        $html[] = '<meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '">';
        if ($image) {
            $html[] = '<meta name="twitter:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">';
        }
        if ($keywords) {
            $html[] = '<meta name="keywords" content="' . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . '">';
        }
        if (!empty($options['extra_meta']) && is_array($options['extra_meta'])) {
            foreach ($options['extra_meta'] as $metaTag) {
                $html[] = $metaTag;
            }
        }
        if (!empty($options['json_ld'])) {
            $html[] = '<script type="application/ld+json">' . json_encode($options['json_ld'], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>';
        }
        return implode("\n    ", $html);
    }
}

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Safe decompression function that handles both compressed and uncompressed content
function safeDecompressContent($content) {
    if (empty($content)) {
        return '';
    }
    
    // Try base64 decode first
    $decoded = base64_decode($content, true);
    if ($decoded !== false) {
        // Try to decompress
        try {
            $decompressed = @gzuncompress($decoded);
            if ($decompressed !== false) {
                return $decompressed;
            }
        } catch (Exception $e) {
            // If decompression fails, return original content
            error_log("Decompression failed in blog-post.php: " . $e->getMessage());
        }
    }
    
    // Return original content if not compressed or decompression failed
    return $content;
}

// Helper function to get full image URL for social sharing (absolute URL)
function getFullImageUrlForSocial($image_path) {
    // Default image path
    $default_image = SITE_URL . '/assets/images/default-post.jpg';
    
    if (empty($image_path)) {
        return $default_image;
    }
    
    // If it's already a full URL
    if (preg_match('/^https?:\/\//', $image_path)) {
        return $image_path;
    }
    
    // Remove any leading slashes
    $image_path = ltrim($image_path, '/');
    
    // If it starts with assets/ or uploads/
    if (strpos($image_path, 'assets/') === 0 || strpos($image_path, 'uploads/') === 0) {
        return SITE_URL . '/' . $image_path;
    }
    
    // Otherwise assume it's just a filename in uploads/images/
    return SITE_URL . '/uploads/images/' . $image_path;
}

// Helper function to get display image URL (relative path for HTML)
function getDisplayImageUrl($image_path) {
    if (empty($image_path)) {
        return 'assets/images/default-post.jpg';
    }
    
    // If it's already a full URL, use it
    if (preg_match('/^https?:\/\//', $image_path)) {
        return $image_path;
    }
    
    // If it starts with assets/ or uploads/
    if (strpos($image_path, 'assets/') === 0 || strpos($image_path, 'uploads/') === 0) {
        return $image_path;
    }
    
    // Otherwise assume it's just a filename in uploads/images/
    return 'uploads/images/' . $image_path;
}

// Helper function to get full post URL
function getFullPostUrl($slug) {
    return SITE_URL . '/blog-post?slug=' . urlencode($slug);
}

// Resolve post slug from query string or pretty URL (/blog-post/my-slug)
function resolveBlogPostSlug() {
    if (!empty($_GET['slug'])) {
        return trim((string) $_GET['slug']);
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (preg_match('#/blog-post/([^/]+)/?$#', $path, $matches)) {
        return urldecode($matches[1]);
    }

    return '';
}

function getBlogListUrl() {
    $base = defined('BASE_PATH') ? BASE_PATH : '';
    return ($base !== '' ? $base : '') . '/blog.php';
}

// Helper function to get excerpt for meta description
function getMetaDescription($excerpt, $content, $length = 160) {
    if (!empty($excerpt)) {
        return strip_tags(html_entity_decode($excerpt, ENT_QUOTES, 'UTF-8'));
    }
    
    $text = strip_tags(html_entity_decode($content, ENT_QUOTES, 'UTF-8'));
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length);
        $last_space = strrpos($text, ' ');
        if ($last_space !== false) {
            $text = substr($text, 0, $last_space);
        }
        $text .= '...';
    }
    
    return $text;
}

// Get post slug from URL
$slug = resolveBlogPostSlug();

if (empty($slug)) {
    header('Location: ' . getBlogListUrl());
    exit();
}

// Get post by slug
$post = getPostBySlug($slug);

if (!$post) {
    header('Location: ' . getBlogListUrl());
    exit();
}

// Decompress content
if (!empty($post['content'])) {
    $post['content'] = safeDecompressContent($post['content']);
}

// Increment view count
incrementPostViews($post['id']);

// Get author info
$author_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$author_stmt->bind_param("i", $post['author_id']);
$author_stmt->execute();
$author_result = $author_stmt->get_result();
$author = $author_result->fetch_assoc();

// Get author thumbnail image uploaded via Admin → Blog Settings (Author Thumbnail field)
$author_profile_image = getSetting('blog_author_thumbnail_image', getSetting('about_profile_image', 'assets/images/profile.jpg'));
$author_name = getSetting('about_name', 'Refreshing Dews');
$author_bio = getSetting('about_bio', 'Hello! I\'m the voice and heart behind Refreshing Dews. I created this space to share honest thoughts, daily experiences, and audio messages that I hope will inspire, encourage, and connect with you on your own journey.');
$author_sidebar_name = getSetting('blog_author_name', 'COMANDA1');

// Get related posts (same category or recent)
$related_stmt = $conn->prepare("SELECT * FROM posts WHERE id != ? AND status = 'published' AND category = ? ORDER BY created_at DESC LIMIT 3");
$related_stmt->bind_param("is", $post['id'], $post['category']);
$related_stmt->execute();
$related_posts = $related_stmt->get_result();

// Process related posts to decompress content for excerpts
$processed_related = [];
if ($related_posts->num_rows > 0) {
    while ($related = $related_posts->fetch_assoc()) {
        if (!empty($related['content'])) {
            $related['content'] = safeDecompressContent($related['content']);
        }
        $processed_related[] = $related;
    }
}

// If no posts in same category, get recent posts
if (empty($processed_related)) {
    $related_stmt = $conn->prepare("SELECT * FROM posts WHERE id != ? AND status = 'published' ORDER BY created_at DESC LIMIT 3");
    $related_stmt->bind_param("i", $post['id']);
    $related_stmt->execute();
    $related_posts = $related_stmt->get_result();
    if ($related_posts->num_rows > 0) {
        while ($related = $related_posts->fetch_assoc()) {
            if (!empty($related['content'])) {
                $related['content'] = safeDecompressContent($related['content']);
            }
            $processed_related[] = $related;
        }
    }
}

// Get comments (if comments table exists)
$comments = [];
$comment_count = 0;
$table_check = $conn->query("SHOW TABLES LIKE 'comments'");
if ($table_check->num_rows > 0) {
    $comment_stmt = $conn->prepare("SELECT * FROM comments WHERE post_id = ? AND status = 'approved' ORDER BY created_at ASC");
    $comment_stmt->bind_param("i", $post['id']);
    $comment_stmt->execute();
    $comments_result = $comment_stmt->get_result();
    
    $all_comments = [];
    while ($comment = $comments_result->fetch_assoc()) {
        $all_comments[] = $comment;
    }
    
    // Organize comments into threads
    $comments = [];
    $replies = [];
    
    foreach ($all_comments as $comment) {
        if ($comment['parent_id'] === null) {
            $comments[] = $comment;
        } else {
            $replies[$comment['parent_id']][] = $comment;
        }
    }
    
    $comment_count = count($all_comments);
}

// Get settings
$site_title = getSetting('site_title', 'Painlesslyf');
$site_description = getSetting('site_description', 'Truth, grace, and the roadmap back to God\'s heart for your life and your marriage.');
$site_logo = getSetting('site_logo', 'assets/logo/painlesslyf-logo.png');
$favicon = getSetting('favicon', 'assets/logo/painlesslyf-logo.png');
$primary_color = getSetting('primary_color', '#C9A962');
$font_family = getSetting('font_family', 'Inter, sans-serif');
$enable_animated_bg = getSetting('enable_animated_background', '0');

// Blog post specific settings
$blog_comments_enabled = getSetting('blog_comments_enabled', '1');
$blog_share_buttons_enabled = getSetting('blog_share_buttons_enabled', '1');
$blog_author_box_enabled = getSetting('blog_author_box_enabled', '1');
$blog_related_posts_enabled = getSetting('blog_related_posts_enabled', '1');
$blog_post_navigation_enabled = getSetting('blog_post_navigation_enabled', '1');
$blog_toc_enabled = getSetting('blog_toc_enabled', '1');
$blog_background_color = getSetting('blog_background_color', '#f9fbf9');
$blog_text_color = getSetting('blog_text_color', '#1a2a1f');
$blog_meta_color = getSetting('blog_meta_color', '#6c757d');
$blog_link_color = getSetting('blog_button_color', '#C9A962');
$blog_link_hover_color = getSetting('blog_button_hover_color', '#2c4a3b');
$blog_code_background = getSetting('blog_code_background', '#2d2d2d');
$blog_code_color = getSetting('blog_code_color', '#f8f9fa');

// Blog/post header (Admin → Blog Settings → Header Live Preview)
$blog_header_bg_type = getSetting('blog_header_background_type', 'gradient');
$blog_header_text_color = getSetting('blog_header_text_color', '#ffffff');
$blog_header_gradient_start = getSetting('blog_header_background_gradient_start', '#1a2744');
$blog_header_gradient_end = getSetting('blog_header_background_gradient_end', '#C9A962');
$blog_header_solid_color = getSetting('blog_header_background_color', '#1a2744');
$blog_header_bg_image = getSetting('blog_header_background_image', '');

$post_header_bg_style = '';
if ($blog_header_bg_type === 'gradient') {
    $post_header_bg_style = "background: linear-gradient(135deg, {$blog_header_gradient_start}, {$blog_header_gradient_end});";
} elseif ($blog_header_bg_type === 'solid') {
    $post_header_bg_style = "background: {$blog_header_solid_color};";
} elseif ($blog_header_bg_type === 'image' && !empty($blog_header_bg_image)) {
    $post_header_bg_style = "background: url('{$blog_header_bg_image}') center/cover no-repeat;";
} else {
    $post_header_bg_style = "background: linear-gradient(135deg, {$blog_header_gradient_start} 0%, {$blog_header_gradient_end} 100%);";
}

// Social media links - Only Facebook, Instagram, Pinterest
$social_links = [
    'facebook' => getSetting('facebook_url', '#'),
    'instagram' => getSetting('instagram_url', '#'),
    'pinterest' => getSetting('pinterest_url', '#')
];

// Get reading time
$word_count = str_word_count(strip_tags($post['content']));
$reading_time = ceil($word_count / 200);

// Generate meta data for social sharing
$meta_title = $post['title'] . ' - ' . $site_title;
$meta_description = getMetaDescription($post['excerpt'], $post['content'], 160);
$meta_image = getFullImageUrlForSocial($post['featured_image'] ?? '');
$meta_url = getFullPostUrl($post['slug']);
$seo_schema = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $post['title'],
        'description' => $meta_description,
        'image' => [$meta_image],
        'datePublished' => date('c', strtotime($post['created_at'])),
        'dateModified' => date('c', strtotime($post['updated_at'] ?? $post['created_at'])),
        'author' => [
            '@type' => 'Person',
            'name' => $author_name
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => $site_title,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => getSeoImageUrl($site_logo)
            ]
        ],
        'mainEntityOfPage' => $meta_url
    ]
];

// Handle comment submission
$comment_success = '';
$comment_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment']) && $blog_comments_enabled == '1') {
    $comment_name = trim($_POST['name'] ?? '');
    $comment_email = trim($_POST['email'] ?? '');
    $comment_content = trim($_POST['comment'] ?? '');
    
    if (empty($comment_name)) {
        $comment_error = 'Please enter your name.';
    } elseif (empty($comment_email) || !filter_var($comment_email, FILTER_VALIDATE_EMAIL)) {
        $comment_error = 'Please enter a valid email address.';
    } elseif (empty($comment_content)) {
        $comment_error = 'Please enter your comment.';
    } else {
        // Check if comments table exists, create if not
        $check_table = $conn->query("SHOW TABLES LIKE 'comments'");
        if ($check_table->num_rows == 0) {
            $create_table = "CREATE TABLE IF NOT EXISTS `comments` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `post_id` int(11) NOT NULL,
                `parent_id` int(11) DEFAULT NULL,
                `name` varchar(100) NOT NULL,
                `email` varchar(100) NOT NULL,
                `comment` text NOT NULL,
                `status` enum('pending','approved','spam') DEFAULT 'pending',
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `post_id` (`post_id`),
                KEY `parent_id` (`parent_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            $conn->query($create_table);
        } else {
            // Check if parent_id column exists, add if not
            $column_check = $conn->query("SHOW COLUMNS FROM `comments` LIKE 'parent_id'");
            if ($column_check->num_rows == 0) {
                $conn->query("ALTER TABLE `comments` ADD `parent_id` int(11) DEFAULT NULL AFTER `post_id`, ADD KEY `parent_id` (`parent_id`)");
            }
        }
        
        $insert_stmt = $conn->prepare("INSERT INTO comments (post_id, name, email, comment, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $insert_stmt->bind_param("isss", $post['id'], $comment_name, $comment_email, $comment_content);
        
        if ($insert_stmt->execute()) {
            $comment_success = 'Your comment has been submitted and is awaiting approval.';
        } else {
            $comment_error = 'Failed to submit comment. Please try again.';
        }
    }
}

// Function to get header background style (uses Admin → Blog Settings header)
function getHeaderStyle() {
    global $post_header_bg_style;
    return $post_header_bg_style;
}

// Function to get author thumbnail URL for the sidebar
function getAuthorAvatarUrl() {
    global $author_profile_image;
    if (empty($author_profile_image)) {
        return 'assets/images/default-avatar.jpg';
    }
    
    if (preg_match('/^https?:\/\//', $author_profile_image)) {
        return $author_profile_image;
    }
    
    return ltrim($author_profile_image, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <?php echo renderSeoMetaTags([
        'title' => $meta_title,
        'description' => $meta_description,
        'canonical' => $meta_url,
        'image' => $meta_image,
        'type' => 'article',
        'keywords' => 'blog article, reflection, story, ' . $post['title'],
        'author' => $author_name,
        'extra_meta' => [
            '<meta property="article:published_time" content="' . htmlspecialchars(date('c', strtotime($post['created_at'])), ENT_QUOTES, 'UTF-8') . '">',
            '<meta property="article:author" content="' . htmlspecialchars($author_name, ENT_QUOTES, 'UTF-8') . '">'
        ],
        'json_ld' => $seo_schema
    ]); ?>
    <meta name="googlebot" content="index, follow">
    
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Prism.js for code highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet" />
    
    <?php echo getPageHeroStyles(); ?>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css?v=20260904">
    
    <style>
        :root {
            --primary: <?php echo $primary_color; ?>;
            --primary-dark: <?php echo $blog_link_hover_color; ?>;
            --primary-light: <?php echo $blog_link_color; ?>;
            --dark: <?php echo $blog_text_color; ?>;
            --light: <?php echo $blog_background_color; ?>;
            --gray: <?php echo $blog_meta_color; ?>;
            --border: #e9ecef;
            --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 25px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 40px rgba(0, 0, 0, 0.15);
            --font-family: <?php echo $font_family; ?>;
            --code-bg: <?php echo $blog_code_background; ?>;
            --code-color: <?php echo $blog_code_color; ?>;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            color: var(--dark);
            background: var(--light);
            line-height: 1.8;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        /* Post Header — dimensions via getPageHeroStyles(); background from Admin → Blog Settings */
        .post-header {
            <?php echo getHeaderStyle(); ?>
            color: <?php echo $blog_header_text_color; ?>;
        }

        <?php if ($enable_animated_bg == '1'): ?>
        .post-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.1"><path d="M10 10 L90 10 L90 90 L10 90 Z" fill="none" stroke="white" stroke-width="2"/><circle cx="50" cy="50" r="20" fill="none" stroke="white" stroke-width="2"/></svg>') repeat;
            background-size: 50px 50px;
            animation: float 20s linear infinite;
            z-index: 0;
            pointer-events: none;
        }

        @keyframes float {
            from { transform: translateY(0); }
            to { transform: translateY(-50px); }
        }
        <?php endif; ?>

        .post-category {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .post-header h1 {
            margin-bottom: 15px;
            line-height: 1.3;
        }

        .post-meta {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 14px;
            opacity: 0.95;
        }

        @media (min-width: 768px) {
            .post-meta {
                gap: 30px;
                font-size: 15px;
            }
        }

        .post-meta i {
            margin-right: 5px;
        }

        /* Post Content */
        .post-content-wrapper {
            padding: 40px 0;
            background: <?php echo $blog_background_color; ?>;
        }

        .post-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        @media (min-width: 992px) {
            .post-layout {
                grid-template-columns: 1fr 300px;
                gap: 40px;
            }
        }

        .post-main {
            background: white;
            border-radius: 15px;
            overflow: visible;
            box-shadow: var(--shadow-sm);
        }

        /* Critical content must stay visible even if AOS fails to load */
        .post-main,
        .post-body {
            opacity: 1 !important;
            transform: none !important;
        }

        .post-featured-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
        }

        @media (min-width: 768px) {
            .post-featured-image {
                height: 400px;
            }
        }

        @media (min-width: 992px) {
            .post-featured-image {
                height: 500px;
            }
        }

        .post-body {
            padding: 25px;
        }

        @media (min-width: 768px) {
            .post-body {
                padding: 40px;
            }
        }

        .post-body h2 {
            font-size: clamp(22px, 5vw, 28px);
            font-weight: 700;
            margin: 25px 0 15px;
            color: var(--dark);
        }

        .post-body h3 {
            font-size: clamp(18px, 4vw, 22px);
            font-weight: 600;
            margin: 20px 0 15px;
            color: var(--dark);
        }

        .post-body p {
            margin-bottom: 20px;
            color: var(--dark);
            font-size: 15px;
        }

        @media (min-width: 768px) {
            .post-body p {
                font-size: 16px;
            }
        }

        .post-body img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin: 20px 0;
        }

        .post-body blockquote {
            border-left: 4px solid var(--primary);
            padding: 15px 20px;
            background: #f8f9fa;
            font-style: italic;
            margin: 25px 0;
            border-radius: 0 10px 10px 0;
        }

        @media (min-width: 768px) {
            .post-body blockquote {
                padding: 20px 30px;
            }
        }

        .post-body blockquote p {
            margin-bottom: 0;
            font-size: 16px;
            color: var(--dark);
        }

        .post-body ul, .post-body ol {
            margin: 20px 0;
            padding-left: 20px;
        }

        .post-body li {
            margin-bottom: 8px;
            font-size: 15px;
        }

        .post-body pre {
            background: var(--code-bg);
            color: var(--code-color);
            padding: 15px;
            border-radius: 10px;
            overflow-x: auto;
            margin: 20px 0;
        }

        @media (min-width: 768px) {
            .post-body pre {
                padding: 20px;
            }
        }

        .post-body code {
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }

        .post-body a {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.3s;
        }

        .post-body a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Table of Contents */
        <?php if ($blog_toc_enabled == '1'): ?>
        .table-of-contents {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .table-of-contents h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .table-of-contents ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .table-of-contents li {
            margin-bottom: 8px;
        }

        .table-of-contents a {
            color: var(--gray);
            text-decoration: none;
            transition: color 0.3s;
            display: block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
        }

        .table-of-contents a:hover {
            color: var(--primary);
            background: white;
        }

        .table-of-contents a[style*="margin-left"] {
            font-size: 13px;
        }
        <?php endif; ?>

        /* Sidebar */
        .post-sidebar {
            position: static;
            align-self: start;
        }

        @media (min-width: 992px) {
            .post-sidebar {
                position: sticky;
                top: 90px;
            }
        }

        .sidebar-widget {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: var(--shadow-sm);
        }

        @media (min-width: 768px) {
            .sidebar-widget {
                padding: 25px;
                margin-bottom: 30px;
            }
        }

        .sidebar-widget h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--border);
            position: relative;
        }

        .sidebar-widget h3::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 40px;
            height: 2px;
            background: var(--primary);
        }

        /* Author Widget */
        <?php if ($blog_author_box_enabled == '1'): ?>
        .author-info {
            text-align: center;
        }

        .author-avatar {
            width: 96px;
            height: 96px;
            border-radius: 14px;
            margin: 0 auto 14px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: 3px solid #ffffff;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(74, 124, 89, 0.15);
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .author-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 0;
        }
        <?php endif; ?>

        /* Share Buttons */
        <?php if ($blog_share_buttons_enabled == '1'): ?>
        .share-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .share-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 16px;
        }

        .share-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .share-btn.facebook { background: #1877f2; }
        .share-btn.twitter { background: #1da1f2; }
        .share-btn.linkedin { background: #0a66c2; }
        .share-btn.pinterest { background: #bd081c; }
        .share-btn.whatsapp { background: #25d366; }
        .share-btn.email { background: var(--gray); }
        <?php endif; ?>

        /* Related Posts */
        <?php if ($blog_related_posts_enabled == '1'): ?>
        .related-post {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .related-post:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .related-post-image {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .related-post-content {
            flex: 1;
            min-width: 0;
        }

        .related-post-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .related-post-title a {
            color: var(--dark);
            text-decoration: none;
            transition: color 0.3s;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .related-post-title a:hover {
            color: var(--primary);
        }

        .related-post-date {
            font-size: 11px;
            color: var(--gray);
        }
        <?php endif; ?>

        /* Tags */
        .post-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 25px;
            align-items: center;
        }

        .post-tags span {
            color: var(--gray);
            font-size: 14px;
        }

        .post-tag {
            padding: 5px 12px;
            background: #f8f9fa;
            color: var(--gray);
            border-radius: 30px;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .post-tag:hover {
            background: var(--primary);
            color: white;
        }

        /* Comments Section */
        <?php if ($blog_comments_enabled == '1'): ?>
        .comments-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: var(--shadow-sm);
        }

        @media (min-width: 768px) {
            .comments-section {
                padding: 40px;
                margin-top: 40px;
            }
        }

        .comments-header {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 25px;
        }

        @media (min-width: 576px) {
            .comments-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .comments-header h2 {
            font-size: 20px;
            font-weight: 700;
        }

        .comment-count {
            background: #f8f9fa;
            padding: 6px 16px;
            border-radius: 30px;
            color: var(--gray);
            font-size: 13px;
            display: inline-block;
        }

        .comment {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border);
        }

        @media (min-width: 576px) {
            .comment {
                flex-direction: row;
                gap: 20px;
            }
        }

        .comment-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
        }

        .comment-content {
            flex: 1;
        }

        .comment-header {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 10px;
        }

        @media (min-width: 576px) {
            .comment-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }

        .comment-author {
            font-weight: 600;
            color: var(--dark);
        }

        .comment-date {
            font-size: 12px;
            color: var(--gray);
        }

        .comment.reply {
            margin-left: 60px;
            border-left: 3px solid var(--primary);
            padding-left: 15px;
            background: rgba(74, 124, 89, 0.05);
        }

        .comment-avatar.admin-reply {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        }

        .admin-author {
            font-weight: 700;
        }

        .admin-badge {
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 8px;
        }

        .comment-form {
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: var(--font-family);
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        @media (min-width: 576px) {
            .btn {
                width: auto;
            }
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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
        <?php endif; ?>

        /* Post Navigation */
        <?php if ($blog_post_navigation_enabled == '1'): ?>
        .post-navigation {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }

        @media (min-width: 576px) {
            .post-navigation {
                flex-direction: row;
                justify-content: space-between;
                gap: 40px;
            }
        }

        .nav-prev, .nav-next {
            flex: 1;
        }

        .nav-prev {
            text-align: left;
        }

        .nav-next {
            text-align: right;
        }

        .nav-link {
            text-decoration: none;
            color: var(--dark);
            display: block;
        }

        .nav-label {
            display: block;
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .nav-title {
            font-weight: 600;
            transition: color 0.3s;
            font-size: 14px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        @media (min-width: 768px) {
            .nav-title {
                font-size: 15px;
            }
        }

        .nav-link:hover .nav-title {
            color: var(--primary);
        }
        <?php endif; ?>

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #4a7c59 0%, #2c4a3b 100%);
            color: white;
            padding: 60px 0 20px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            margin-bottom: 40px;
        }

        @media (max-width: 992px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        .footer-logo img {
            max-width: 150px;
            margin-bottom: 15px;
            background: white;
            padding: 10px;
            border-radius: 10px;
        }

        .footer-description {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .footer-social {
            display: flex;
            gap: 15px;
        }

        .footer-social a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .footer-social a:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }

        .footer-col h3 {
            font-size: 18px;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-col h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
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
            background: <?php echo $blog_background_color; ?>;
        }

        ::-webkit-scrollbar-thumb {
            background: <?php echo $primary_color; ?>;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: <?php echo $blog_link_hover_color; ?>;
        }

        /* Touch Optimizations */
        @media (hover: none) {
            .btn:hover,
            .share-btn:hover,
            .nav-link:hover .nav-title {
                transform: none;
            }
        }

        /* Print Styles */
        @media print {
            .modern-navbar, .mobile-drawer, .mobile-drawer-overlay,
            .post-sidebar,
            .comments-section,
            .post-navigation,
            .footer,
            .btn,
            .share-btn {
                display: none;
            }
            
            body {
                padding: 0;
                background: white;
            }
            
            .post-header {
                margin: 0;
                padding: 20px;
                color: black !important;
                background: white !important;
            }
            
            .post-main {
                box-shadow: none;
            }
        }

        <?php echo getSetting('custom_css', ''); ?>
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <!-- Post Header -->
    <header class="post-header">
        <div class="post-header-content" data-aos="fade-up">
            <span class="post-category"><?php echo htmlspecialchars($post['category'] ?? 'Blog Post'); ?></span>
            <h1><?php echo htmlspecialchars($post['title']); ?></h1>
            <div class="post-meta">
                <span><i class="far fa-user"></i> <?php echo htmlspecialchars($author_name); ?></span>
                <span><i class="far fa-calendar"></i> <?php echo date('F j, Y', strtotime($post['created_at'])); ?></span>
                <span><i class="far fa-clock"></i> <?php echo $reading_time; ?> min read</span>
                <span><i class="far fa-eye"></i> <?php echo number_format($post['views']); ?> views</span>
                <?php if ($blog_comments_enabled == '1'): ?>
                <span><i class="far fa-comment"></i> <?php echo $comment_count; ?> comments</span>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Post Content -->
    <section class="post-content-wrapper">
        <div class="container">
            <div class="post-layout">
                <!-- Main Content -->
                <article class="post-main">
                    <?php if (!empty($post['featured_image'])): ?>
                    <img src="<?php echo getDisplayImageUrl($post['featured_image']); ?>" 
                         alt="<?php echo htmlspecialchars($post['title']); ?>" 
                         class="post-featured-image"
                         loading="lazy">
                    <button type="button" class="read-aloud-btn" data-target=".post-body" style="margin: 14px 0 18px; display: inline-flex; align-items: center; gap: 8px; background: #dc3545; color: #fff; border: none; border-radius: 999px; padding: 10px 16px; font-size: 0.92rem; font-weight: 600; cursor: pointer; box-shadow: 0 8px 18px rgba(220, 53, 69, 0.2);">
                        <i class="fas fa-volume-up"></i> Read Aloud
                    </button>
                    <?php endif; ?>
                    
                    <div class="post-body">
                        <?php echo $post['content']; ?>
                        
                        <!-- Tags -->
                        <div class="post-tags">
                            <span><i class="fas fa-tags"></i> Tags:</span>
                            <a href="blog.php?category=<?php echo urlencode($post['category'] ?? 'Life'); ?>" class="post-tag"><?php echo htmlspecialchars($post['category'] ?? 'Life'); ?></a>
                        </div>
                    </div>
                </article>
                
                <!-- Sidebar -->
                <aside class="post-sidebar" data-aos="fade-left">
                    <!-- Author Widget -->
                    <?php if ($blog_author_box_enabled == '1'): ?>
                    <div class="sidebar-widget">
                        <h3>The Author</h3>
                        <div class="author-info">
                            <div class="author-avatar">
                                <img src="<?php echo getAuthorAvatarUrl(); ?>" 
                                     alt="<?php echo htmlspecialchars($author_sidebar_name); ?>"
                                     loading="lazy">
                            </div>
                            <div class="author-name"><?php echo htmlspecialchars($author_sidebar_name); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Share Widget -->
                    <?php if ($blog_share_buttons_enabled == '1'): ?>
                    <div class="sidebar-widget">
                        <h3>Share this Post</h3>
                        <div class="share-buttons">
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($meta_url); ?>" target="_blank" class="share-btn facebook" aria-label="Share on Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode($meta_url); ?>&text=<?php echo urlencode($post['title']); ?>" target="_blank" class="share-btn twitter" aria-label="Share on Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode($meta_url); ?>&title=<?php echo urlencode($post['title']); ?>" target="_blank" class="share-btn linkedin" aria-label="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                            <a href="https://pinterest.com/pin/create/button/?url=<?php echo urlencode($meta_url); ?>&media=<?php echo urlencode($meta_image); ?>&description=<?php echo urlencode($post['title']); ?>" target="_blank" class="share-btn pinterest" aria-label="Share on Pinterest"><i class="fab fa-pinterest"></i></a>
                            <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($post['title'] . ' - ' . $meta_url); ?>" target="_blank" class="share-btn whatsapp" aria-label="Share on WhatsApp"><i class="fab fa-whatsapp"></i></a>
                            <a href="mailto:?subject=<?php echo urlencode($post['title']); ?>&body=<?php echo urlencode('Check out this post: ' . $meta_url); ?>" class="share-btn email" aria-label="Share via Email"><i class="fas fa-envelope"></i></a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Related Posts Widget -->
                    <?php if ($blog_related_posts_enabled == '1' && !empty($processed_related)): ?>
                    <div class="sidebar-widget">
                        <h3>Related Posts</h3>
                        <?php foreach ($processed_related as $related): ?>
                        <div class="related-post">
                            <img src="<?php echo getDisplayImageUrl($related['featured_image'] ?? ''); ?>" 
                                 alt="<?php echo htmlspecialchars($related['title']); ?>" 
                                 class="related-post-image"
                                 loading="lazy">
                            <div class="related-post-content">
                                <div class="related-post-title">
                                    <a href="blog-post.php?slug=<?php echo $related['slug']; ?>"><?php echo htmlspecialchars($related['title']); ?></a>
                                </div>
                                <div class="related-post-date">
                                    <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($related['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </aside>
            </div>
            
            <!-- Comments Section -->
            <?php if ($blog_comments_enabled == '1'): ?>
            <div class="comments-section" data-aos="fade-up">
                <div class="comments-header">
                    <h2>Comments</h2>
                    <span class="comment-count"><?php echo $comment_count; ?> Comments</span>
                </div>
                
                <?php if (!empty($comments)): ?>
                    <?php foreach ($comments as $comment): ?>
                    <div class="comment">
                        <div class="comment-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="comment-content">
                            <div class="comment-header">
                                <span class="comment-author"><?php echo htmlspecialchars($comment['name']); ?></span>
                                <span class="comment-date"><?php echo date('M d, Y', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <div class="comment-text">
                                <?php echo nl2br(htmlspecialchars($comment['comment'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php 
                    // Display replies for this comment
                    if (isset($replies[$comment['id']])): 
                        foreach ($replies[$comment['id']] as $reply): 
                    ?>
                    <div class="comment reply">
                        <div class="comment-avatar admin-reply">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="comment-content">
                            <div class="comment-header">
                                <span class="comment-author admin-author"><?php echo htmlspecialchars($reply['name']); ?> <span class="admin-badge">Admin</span></span>
                                <span class="comment-date"><?php echo date('M d, Y', strtotime($reply['created_at'])); ?></span>
                            </div>
                            <div class="comment-text">
                                <?php echo nl2br(htmlspecialchars($reply['comment'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--gray); padding: 30px 0;">No comments yet. Be the first to comment!</p>
                <?php endif; ?>
                
                <!-- Comment Form -->
                <div class="comment-form">
                    <h3 style="margin-bottom: 20px; font-size: 18px;">Leave a Comment</h3>
                    
                    <?php if ($comment_success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($comment_success); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($comment_error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($comment_error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="name">Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email * (will not be published)</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="comment">Comment *</label>
                            <textarea class="form-control" id="comment" name="comment" required></textarea>
                        </div>
                        
                        <button type="submit" name="submit_comment" class="btn btn-primary">Post Comment</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Post Navigation -->
            <?php if ($blog_post_navigation_enabled == '1'): ?>
            <?php
            // Get previous and next posts
            $prev_stmt = $conn->prepare("SELECT slug, title FROM posts WHERE id < ? AND status = 'published' ORDER BY id DESC LIMIT 1");
            $prev_stmt->bind_param("i", $post['id']);
            $prev_stmt->execute();
            $prev_post = $prev_stmt->get_result()->fetch_assoc();
            
            $next_stmt = $conn->prepare("SELECT slug, title FROM posts WHERE id > ? AND status = 'published' ORDER BY id ASC LIMIT 1");
            $next_stmt->bind_param("i", $post['id']);
            $next_stmt->execute();
            $next_post = $next_stmt->get_result()->fetch_assoc();
            ?>
            
            <?php if ($prev_post || $next_post): ?>
            <div class="post-navigation">
                <?php if ($prev_post): ?>
                <div class="nav-prev">
                    <a href="blog-post.php?slug=<?php echo $prev_post['slug']; ?>" class="nav-link">
                        <span class="nav-label"><i class="fas fa-arrow-left"></i> Previous Post</span>
                        <span class="nav-title"><?php echo htmlspecialchars($prev_post['title']); ?></span>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($next_post): ?>
                <div class="nav-next">
                    <a href="blog-post.php?slug=<?php echo $next_post['slug']; ?>" class="nav-link">
                        <span class="nav-label">Next Post <i class="fas fa-arrow-right"></i></span>
                        <span class="nav-title"><?php echo htmlspecialchars($next_post['title']); ?></span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
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

        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });

        <?php if ($blog_toc_enabled == '1'): ?>
        // Generate table of contents
        document.addEventListener('DOMContentLoaded', function() {
            const headings = document.querySelectorAll('.post-body h2, .post-body h3');
            if (headings.length > 2) {
                const toc = document.createElement('div');
                toc.className = 'table-of-contents';
                toc.innerHTML = '<h3>Table of Contents</h3><ul></ul>';
                
                headings.forEach((heading, index) => {
                    const id = 'heading-' + index;
                    heading.id = id;
                    
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = '#' + id;
                    a.textContent = heading.textContent;
                    if (heading.tagName === 'H3') {
                        a.style.marginLeft = '20px';
                    }
                    
                    li.appendChild(a);
                    toc.querySelector('ul').appendChild(li);
                });
                
                const postBody = document.querySelector('.post-body');
                if (postBody) {
                    postBody.prepend(toc);
                }
            }
        });
        <?php endif; ?>

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

        // Add loading skeleton for featured image
        const featuredImg = document.querySelector('.post-featured-image');
        if (featuredImg && !featuredImg.complete) {
            featuredImg.classList.add('skeleton');
            featuredImg.addEventListener('load', () => {
                featuredImg.classList.remove('skeleton');
            });
            featuredImg.addEventListener('error', () => {
                featuredImg.classList.remove('skeleton');
            });
        }

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