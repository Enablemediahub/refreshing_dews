<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/db-connection.php';

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
            error_log("Decompression failed in blog.php: " . $e->getMessage());
        }
    }
    
    // Return original content if not compressed or decompression failed
    return $content;
}

// Function to get featured post (manually selected or most viewed)
function getFeaturedPost() {
    global $conn;
    
    // First, try to get manually featured post
    $stmt = $conn->prepare("SELECT id, title, slug, content, featured_image, excerpt, category, author_id, views, created_at, updated_at, is_featured 
                            FROM posts 
                            WHERE status = 'published' AND is_featured = 1 
                            LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        // Decompress content if needed
        if (!empty($row['content'])) {
            $row['content'] = safeDecompressContent($row['content']);
        }
        return $row;
    }
    
    // Fallback to most viewed if no featured post is set
    $stmt = $conn->prepare("SELECT id, title, slug, content, featured_image, excerpt, category, author_id, views, created_at, updated_at, is_featured 
                            FROM posts 
                            WHERE status = 'published' 
                            ORDER BY views DESC, created_at DESC 
                            LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        if (!empty($row['content'])) {
            $row['content'] = safeDecompressContent($row['content']);
        }
        return $row;
    }
    
    return null;
}

// Function to get all categories with post counts
function getAllCategoriesWithCounts() {
    global $conn;
    
    $categories = [];
    $stmt = $conn->prepare("SELECT category, COUNT(*) as count 
                            FROM posts 
                            WHERE status = 'published' AND category IS NOT NULL AND category != '' 
                            GROUP BY category 
                            ORDER BY count DESC, category ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = [
                'name' => $row['category'],
                'count' => $row['count']
            ];
        }
    }
    
    return $categories;
}

// Function to get all posts with pagination and filters
function getAllPostsWithFilters($page = 1, $per_page = 9, $search = '', $category = '') {
    global $conn;
    
    $offset = ($page - 1) * $per_page;
    
    // Base query - order by created_at DESC (latest first)
    $sql = "SELECT * FROM posts WHERE status = 'published'";
    $count_sql = "SELECT COUNT(*) as total FROM posts WHERE status = 'published'";
    
    // Add search filter
    if (!empty($search)) {
        $search_term = "%{$search}%";
        $sql .= " AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
        $count_sql .= " AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    }
    
    // Add category filter
    if (!empty($category)) {
        $sql .= " AND category = ?";
        $count_sql .= " AND category = ?";
    }
    
    // Order by is_featured first, then created_at DESC (latest first)
    $sql .= " ORDER BY is_featured DESC, created_at DESC LIMIT ? OFFSET ?";
    
    // Prepare statements
    $stmt = $conn->prepare($sql);
    $count_stmt = $conn->prepare($count_sql);
    
    // Bind parameters
    if (!empty($search) && !empty($category)) {
        $stmt->bind_param("sssssii", $search_term, $search_term, $search_term, $category, $per_page, $offset);
        $count_stmt->bind_param("sssss", $search_term, $search_term, $search_term, $category);
    } elseif (!empty($search)) {
        $stmt->bind_param("ssssii", $search_term, $search_term, $search_term, $per_page, $offset);
        $count_stmt->bind_param("sss", $search_term, $search_term, $search_term);
    } elseif (!empty($category)) {
        $stmt->bind_param("sii", $category, $per_page, $offset);
        $count_stmt->bind_param("s", $category);
    } else {
        $stmt->bind_param("ii", $per_page, $offset);
    }
    
    // Execute and get results
    $stmt->execute();
    $result = $stmt->get_result();
    $posts = [];
    while ($row = $result->fetch_assoc()) {
        // Decompress content if needed
        if (!empty($row['content'])) {
            $row['content'] = safeDecompressContent($row['content']);
        }
        $posts[] = $row;
    }
    
    // Get total count
    if (!empty($search) || !empty($category)) {
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total = $count_result->fetch_assoc()['total'];
        $count_stmt->close();
    } else {
        $total_result = $conn->query("SELECT COUNT(*) as total FROM posts WHERE status = 'published'");
        $total = $total_result->fetch_assoc()['total'];
    }
    
    $stmt->close();
    
    return [
        'posts' => $posts,
        'total' => $total
    ];
}

// Get settings
$site_title = getSetting('site_title', 'Painlesslyf');
$site_description = getSetting('site_description', 'Truth, grace, and the roadmap back to God\'s heart for your life and your marriage.');
$site_logo = getSetting('site_logo', 'assets/logo/painlesslyf-logo.png');
$favicon = getSetting('favicon', 'assets/logo/painlesslyf-logo.png');

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = getSetting('blog_per_page', 9);

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

// Get all published posts with pagination and filters
$posts_data = getAllPostsWithFilters($page, $per_page, $search, $category_filter);
$posts = $posts_data['posts'];
$total_posts = $posts_data['total'];
$total_pages = ceil($total_posts / $per_page);

// Get featured post (manually selected or most viewed)
$featured_post = getFeaturedPost();

// Get all unique categories with counts
$categories = getAllCategoriesWithCounts();

// Get recent posts for sidebar (using function from functions.php)
$recent_posts = getRecentPosts(5);

// Social media links - Only Facebook, Instagram, Pinterest
$social_links = [
    'facebook' => getSetting('facebook_url', '#'),
    'instagram' => getSetting('instagram_url', '#'),
    'pinterest' => getSetting('pinterest_url', '#')
];

// Theme colors (navy & gold — matches homepage)
$theme_blue = getSetting('theme_blue_color', '#1a2744');
$theme_blue_dark = getSetting('theme_blue_dark_color', '#0f1824');
$theme_green = getSetting('theme_green_color', '#C9A962');
$theme_green_dark = getSetting('theme_green_dark_color', '#A68844');
$shared_button_color = getSetting('shared_button_color', '#2563eb');
$shared_button_hover_color = getSetting('shared_button_hover_color', '#1d4ed8');
$shared_button_text_color = getSetting('shared_button_text_color', '#ffffff');

// Blog header settings
$blog_header_bg_type = getSetting('blog_header_background_type', 'gradient');
$blog_header_text_color = getSetting('blog_header_text_color', '#ffffff');
$blog_header_gradient_start = getSetting('blog_header_background_gradient_start', $theme_blue);
$blog_header_gradient_end = getSetting('blog_header_background_gradient_end', $theme_green);
$blog_header_solid_color = getSetting('blog_header_background_color', '#1a2744');
$blog_header_bg_image = getSetting('blog_header_background_image', '');

// Sidebar settings
$blog_sidebar_enabled = getSetting('blog_sidebar_enabled', '1');
$blog_recent_posts_enabled = getSetting('blog_recent_posts_enabled', '1');

// Newsletter settings
$blog_newsletter_enabled = getSetting('blog_newsletter_enabled', '1');
$blog_newsletter_title = getSetting('blog_newsletter_title', 'Never Miss a Post');
$blog_newsletter_subtitle = getSetting('blog_newsletter_subtitle', 'Subscribe to get the latest articles and audio messages delivered to your inbox.');
$blog_newsletter_bg_type = getSetting('blog_newsletter_background_type', 'gradient');
$blog_newsletter_text_color = getSetting('blog_newsletter_text_color', '#ffffff');
$blog_newsletter_gradient_start = getSetting('blog_newsletter_gradient_start', $theme_blue);
$blog_newsletter_gradient_end = getSetting('blog_newsletter_gradient_end', $theme_green);
$blog_newsletter_solid_color = getSetting('blog_newsletter_background_color', '#1a2744');
$blog_newsletter_bg_image = getSetting('blog_newsletter_background_image', '');
$blog_newsletter_button_bg = getSetting('blog_newsletter_button_bg', '#ffffff');
$blog_newsletter_button_text = getSetting('blog_newsletter_button_text', $theme_blue);

// Color settings
$blog_background_color = getSetting('blog_background_color', '#faf7f0');
$blog_card_background = getSetting('blog_card_background', '#ffffff');
$blog_title_color = getSetting('blog_title_color', '#1e4d72');
$blog_text_color = getSetting('blog_text_color', '#6b6b6b');
$blog_meta_color = getSetting('blog_meta_color', '#6b6b6b');
$blog_category_bg = getSetting('blog_category_background', $theme_green);
$blog_category_color = getSetting('blog_category_color', '#ffffff');
$blog_button_color = getSetting('blog_button_color', $theme_blue);
$blog_button_hover_color = getSetting('blog_button_hover_color', $theme_blue_dark);

// Build header background style
$header_bg_style = '';
if ($blog_header_bg_type == 'gradient') {
    $header_bg_style = "background: linear-gradient(135deg, {$blog_header_gradient_start}, {$blog_header_gradient_end});";
} elseif ($blog_header_bg_type == 'solid') {
    $header_bg_style = "background: {$blog_header_solid_color};";
} elseif ($blog_header_bg_type == 'image' && !empty($blog_header_bg_image)) {
    $header_bg_style = "background: url('{$blog_header_bg_image}') center/cover no-repeat;";
} else {
    $header_bg_style = "background: linear-gradient(135deg, {$theme_blue} 0%, {$theme_green} 100%);";
}

// Build newsletter background style
$newsletter_bg_style = '';
if ($blog_newsletter_bg_type == 'gradient') {
    $newsletter_bg_style = "background: linear-gradient(135deg, {$theme_blue} 0%, {$theme_green} 100%);";
} elseif ($blog_newsletter_bg_type == 'solid') {
    $newsletter_bg_style = "background: {$blog_newsletter_solid_color};";
} elseif ($blog_newsletter_bg_type == 'image' && !empty($blog_newsletter_bg_image)) {
    $newsletter_bg_style = "background: url('{$blog_newsletter_bg_image}') center/cover no-repeat;";
} else {
    $newsletter_bg_style = "background: linear-gradient(135deg, {$theme_blue} 0%, {$theme_green} 100%);";
}

// Determine if we should show featured post
$show_featured = ($featured_post && $page == 1 && empty($search) && empty($category_filter));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Blog - <?php echo htmlspecialchars($site_title); ?></title>
    
    <!-- Meta Tags -->
    <meta name="description" content="<?php echo htmlspecialchars($site_description); ?>">
    <meta name="keywords" content="marriage, faith, grace, Christian blog, truth, divine assignment">
    <meta name="author" content="Painlesslyf">
    
    <!-- Open Graph Tags -->
    <meta property="og:title" content="Blog - <?php echo htmlspecialchars($site_title); ?>">
    <meta property="og:description" content="Read the latest thoughts, stories, and experiences">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/<?php echo $site_logo; ?>">
    <meta property="og:url" content="<?php echo SITE_URL; ?>/blog.php">
    <meta property="og:type" content="website">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $favicon; ?>">
    
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
            --shared-button-color: <?php echo $shared_button_color; ?>;
            --shared-button-hover-color: <?php echo $shared_button_hover_color; ?>;
            --shared-button-text-color: <?php echo $shared_button_text_color; ?>;
            --primary: <?php echo $blog_button_color; ?>;
            --primary-dark: <?php echo $blog_button_hover_color; ?>;
            --dark: <?php echo $blog_title_color; ?>;
            --light: <?php echo $blog_background_color; ?>;
            --gray: <?php echo $blog_text_color; ?>;
            --border: rgba(37, 99, 235, 0.12);
            --shadow-sm: 0 4px 20px rgba(37, 99, 235, 0.08);
            --shadow-md: 0 8px 30px rgba(37, 99, 235, 0.12);
            --shadow-lg: 0 20px 50px rgba(37, 99, 235, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--dark);
            background: var(--light);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Blog Header — dimensions via getPageHeroStyles(); background set per page */
        .blog-header {
            <?php echo $header_bg_style; ?>
            color: <?php echo $blog_header_text_color; ?>;
        }

        .blog-header h1 {
            animation: fadeInUp 1s ease;
            color: inherit;
        }

        .blog-header p {
            animation: fadeInUp 1s ease 0.2s both;
            color: inherit;
        }

        .blog-search {
            max-width: 500px;
            margin: 0 auto;
            position: relative;
            animation: fadeInUp 1s ease 0.4s both;
        }

        .blog-search input {
            width: 100%;
            padding: 18px 25px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }

        .blog-search input:focus {
            outline: none;
            box-shadow: 0 0 0 5px rgba(255, 255, 255, 0.3);
        }

        .blog-search button {
            position: absolute;
            right: 8px;
            top: 8px;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.35);
        }

        .blog-search button:hover {
            transform: scale(1.08);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.45);
        }

        /* Category Filter */
        .category-filter {
            background: white;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 80px;
            z-index: 100;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .filter-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .filter-btn {
            padding: 10px 25px;
            border: 2px solid var(--border);
            background: white;
            color: var(--dark);
            border-radius: 30px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: var(--shared-button-color);
            border-color: var(--shared-button-color);
            color: var(--shared-button-text-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.25);
        }

        .filter-count {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 12px;
        }

        .filter-btn.active .filter-count {
            background: rgba(255,255,255,0.2);
        }

        /* Blog Layout */
        .blog-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 40px;
            padding: 60px 0;
        }

        @media (max-width: 992px) {
            .blog-layout {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }

        /* Featured Post */
        .featured-post {
            margin-bottom: 60px;
        }

        .featured-card {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 40px;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.9);
            transition: var(--transition);
        }

        .featured-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 24px 60px rgba(37, 99, 235, 0.18);
            border-color: rgba(37, 99, 235, 0.25);
        }

        .featured-image {
            height: 400px;
            overflow: hidden;
            position: relative;
        }

        .featured-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .featured-card:hover .featured-image img {
            transform: scale(1.1);
        }

        .featured-content {
            padding: 40px 40px 40px 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .featured-badge {
            background: var(--shared-button-color);
            color: var(--shared-button-text-color);
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            width: fit-content;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
        }

        .featured-badge i {
            font-size: 12px;
        }

        .featured-content h2 {
            font-size: 32px;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            margin-bottom: 15px;
            color: var(--theme-blue);
            line-height: 1.3;
        }

        .featured-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            color: var(--gray);
            font-size: 14px;
            flex-wrap: wrap;
        }

        .featured-meta i {
            color: var(--theme-green);
            margin-right: 5px;
        }

        .featured-excerpt {
            font-size: 16px;
            color: var(--gray);
            margin-bottom: 25px;
            line-height: 1.8;
        }

        .read-more-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 30px;
            background: var(--shared-button-color);
            color: var(--shared-button-text-color);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: var(--transition);
            width: fit-content;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.3);
        }

        .read-more-btn:hover {
            gap: 15px;
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(37, 99, 235, 0.4);
            background: var(--shared-button-hover-color);
            color: var(--shared-button-text-color);
        }

        /* Blog Grid */
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }

        @media (max-width: 768px) {
            .blog-grid {
                grid-template-columns: 1fr;
            }
        }

        .blog-card {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 0;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(201, 169, 98, 0.2);
            transition: var(--transition);
            position: relative;
        }

        .blog-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(26, 39, 68, 0.22);
            background: linear-gradient(135deg, #1a2744 0%, #0f1824 100%);
            border-color: #C9A962;
        }

        .blog-card:hover .card-title a,
        .blog-card:hover .card-excerpt,
        .blog-card:hover .read-link,
        .blog-card:hover .card-meta {
            color: rgba(255, 255, 255, 0.92) !important;
        }

        .blog-card:hover .card-meta i,
        .blog-card:hover .read-link i {
            color: rgba(255, 255, 255, 0.85);
        }

        .blog-card:hover .card-footer {
            border-top-color: rgba(255, 255, 255, 0.2);
        }

        .card-image {
            aspect-ratio: 1 / 1;
            overflow: hidden;
            position: relative;
        }

        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0;
            transition: transform 0.5s ease;
        }

        .blog-card:hover .card-image img {
            transform: scale(1.1);
        }

        .card-category {
            position: absolute;
            top: 15px;
            left: 15px;
            background: <?php echo $blog_category_bg; ?>;
            color: <?php echo $blog_category_color; ?>;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            z-index: 2;
        }

        .featured-tag {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: #ffffff;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-content {
            padding: 20px;
        }

        .card-meta {
            display: flex;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 12px;
            color: <?php echo $blog_meta_color; ?>;
            flex-wrap: wrap;
        }

        .card-meta i {
            color: var(--theme-green);
            margin-right: 3px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        .card-title a {
            color: var(--theme-blue);
            text-decoration: none;
            transition: var(--transition);
        }

        .card-title a:hover {
            color: var(--theme-blue-dark);
        }

        .card-excerpt {
            font-size: 13px;
            color: var(--gray);
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .read-link {
            color: var(--theme-blue);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: var(--transition);
        }

        .read-link:hover {
            gap: 8px;
            color: var(--theme-blue-dark);
        }

        .card-stats {
            display: flex;
            gap: 10px;
            color: var(--gray);
            font-size: 12px;
        }

        /* Sidebar */
        .blog-sidebar {
            position: sticky;
            top: 100px;
            align-self: start;
        }

        .sidebar-widget {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-sm);
        }

        .sidebar-widget h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
            position: relative;
        }

        .sidebar-widget h3::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 50px;
            height: 2px;
            background: linear-gradient(90deg, var(--theme-blue), var(--theme-green));
        }

        /* Category List */
        .category-list {
            list-style: none;
        }

        .category-list li {
            margin-bottom: 12px;
        }

        .category-list a {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--dark);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 10px;
            transition: var(--transition);
            background: #f8f9fa;
        }

        .category-list a:hover,
        .category-list a.active {
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
            transform: translateX(5px);
        }

        .category-list .count {
            background: rgba(0,0,0,0.1);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 12px;
        }

        .category-list a:hover .count,
        .category-list a.active .count {
            background: rgba(255,255,255,0.2);
        }

        /* Recent Posts */
        .recent-post {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .recent-post:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .recent-post-image {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .recent-post-content {
            flex: 1;
        }

        .recent-post-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
            line-height: 1.4;
        }

        .recent-post-title a {
            color: var(--dark);
            text-decoration: none;
            transition: color 0.3s;
        }

        .recent-post-title a:hover {
            color: var(--theme-blue);
        }

        .recent-post-date {
            font-size: 11px;
            color: var(--gray);
        }

        /* Pagination */
        .pagination-section {
            margin-top: 40px;
            text-align: center;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            list-style: none;
            flex-wrap: wrap;
        }

        .page-item {
            display: inline-block;
        }

        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            height: 42px;
            padding: 0 12px;
            background: white;
            border: 2px solid var(--border);
            border-radius: 12px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .page-link:hover,
        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            border-color: transparent;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.25);
        }

        .page-link:hover {
            transform: translateY(-2px);
        }

        .page-item.active .page-link {
            transform: none;
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Newsletter Section */
        .newsletter-section {
            padding: 80px 0;
            <?php echo $newsletter_bg_style; ?>
            color: <?php echo $blog_newsletter_text_color; ?>;
            position: relative;
            overflow: hidden;
        }

        <?php if ($blog_newsletter_bg_type === 'image' && !empty($blog_newsletter_bg_image)): ?>
        .newsletter-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1;
        }
        <?php endif; ?>

        .newsletter-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            margin: 0 auto;
            padding: 0 20px;
            text-align: center;
        }

        .newsletter-content h2 {
            font-size: 36px;
            font-weight: 700;
            font-family: 'Playfair Display', serif;
            margin-bottom: 15px;
            color: inherit;
        }

        .newsletter-content p {
            font-size: 18px;
            opacity: 0.95;
            margin-bottom: 30px;
            color: inherit;
        }

        .newsletter-form {
            display: flex;
            gap: 10px;
            max-width: 500px;
            margin: 0 auto;
        }

        .newsletter-form input {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 50px;
            font-size: 16px;
        }

        .newsletter-form button {
            padding: 15px 30px;
            background: <?php echo $blog_newsletter_button_bg; ?>;
            color: <?php echo $blog_newsletter_button_text; ?>;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .newsletter-form button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .newsletter-form {
                flex-direction: column;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Active Filter Indicator */
        .active-filter {
            background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            display: inline-block;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.2);
        }

        .clear-filter {
            color: white;
            text-decoration: none;
            margin-left: 10px;
            font-weight: 600;
        }

        .clear-filter:hover {
            text-decoration: underline;
        }

        /* No Posts Message */
        .no-posts {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 20px;
        }

        .no-posts i {
            font-size: 60px;
            color: var(--theme-blue);
            margin-bottom: 20px;
            opacity: 0.45;
        }

        .no-posts h3 {
            font-size: 24px;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .no-posts p {
            color: var(--gray);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .featured-card {
                grid-template-columns: 1fr;
            }
            .featured-content {
                padding: 30px;
            }
        }

        @media (max-width: 768px) {
            .filter-container {
                gap: 10px;
            }
            .filter-btn {
                padding: 8px 16px;
                font-size: 13px;
            }
            .featured-content h2 {
                font-size: 24px;
            }
            .featured-meta {
                flex-direction: column;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Blog Header -->
    <header class="blog-header">
        <div class="blog-header-content">
            <h1 data-aos="fade-up"><?php echo htmlspecialchars(getSetting('blog_header_title', 'The Blog')); ?></h1>
            <p data-aos="fade-up" data-aos-delay="100"><?php echo htmlspecialchars(getSetting('blog_header_subtitle', 'Thoughts, stories, and experiences from the everyday. Honest and unfiltered.')); ?></p>
            <div class="blog-search" data-aos="fade-up" data-aos-delay="200">
                <form action="blog.php" method="GET">
                    <input type="text" name="search" placeholder="Search articles..." value="<?php echo htmlspecialchars($search); ?>">
                    <?php if (!empty($category_filter)): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
                    <?php endif; ?>
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </header>

    <!-- Category Filter -->
    <div class="category-filter">
        <div class="filter-container">
            <a href="blog.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" class="filter-btn <?php echo empty($category_filter) ? 'active' : ''; ?>">
                All Posts <span class="filter-count"><?php echo $total_posts; ?></span>
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="blog.php?category=<?php echo urlencode($cat['name']); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-btn <?php echo ($category_filter == $cat['name']) ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat['name']); ?>
                <span class="filter-count"><?php echo $cat['count']; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Active Filters -->
    <?php if (!empty($search) || !empty($category_filter)): ?>
    <div class="container" style="padding-top: 30px;">
        <div class="active-filter">
            <i class="fas fa-filter"></i> 
            Showing: 
            <?php if (!empty($category_filter)): ?>
                Category: <?php echo htmlspecialchars($category_filter); ?>
            <?php endif; ?>
            <?php if (!empty($search)): ?>
                <?php if (!empty($category_filter)): echo ' & '; endif; ?>
                Search: "<?php echo htmlspecialchars($search); ?>"
            <?php endif; ?>
            <a href="blog.php" class="clear-filter"><i class="fas fa-times"></i> Clear filters</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Area -->
    <div class="container">
        <div class="blog-layout">
            <!-- Main Content -->
            <div class="blog-main">
                <!-- Featured Post -->
                <?php if ($show_featured && $featured_post): ?>
                <div class="featured-post" data-aos="fade-up">
                    <div class="featured-card">
                        <div class="featured-image">
                            <img src="<?php echo !empty($featured_post['featured_image']) ? 'uploads/images/' . $featured_post['featured_image'] : 'assets/images/default-post.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($featured_post['title']); ?>">
                        </div>
                        <div class="featured-content">
                            <span class="featured-badge">
                                <i class="fas fa-star"></i> Featured Post
                            </span>
                            <h2><?php echo htmlspecialchars($featured_post['title']); ?></h2>
                            <div class="featured-meta">
                                <span><i class="far fa-calendar"></i> <?php echo date('F j, Y', strtotime($featured_post['created_at'])); ?></span>
                                <span><i class="far fa-eye"></i> <?php echo number_format($featured_post['views'] ?? 0); ?> views</span>
                                <span><i class="far fa-clock"></i> <?php echo ceil(str_word_count(strip_tags($featured_post['content'])) / 200); ?> min read</span>
                                <span><i class="far fa-folder"></i> <?php echo htmlspecialchars($featured_post['category'] ?? 'Uncategorized'); ?></span>
                            </div>
                            <p class="featured-excerpt"><?php echo htmlspecialchars(truncateText($featured_post['excerpt'] ?: strip_tags($featured_post['content']), 200)); ?></p>
                            <a href="blog-post.php?slug=<?php echo $featured_post['slug']; ?>" class="read-more-btn">
                                Read Full Article <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Blog Grid -->
                <?php if (!empty($posts)): ?>
                <div class="blog-grid">
                    <?php 
                    // Skip featured post if showing featured and it's the first post
                    $display_posts = $posts;
                    if ($show_featured && $featured_post && isset($posts[0]) && $posts[0]['id'] == $featured_post['id']) {
                        $display_posts = array_slice($posts, 1);
                    }
                    
                    foreach ($display_posts as $index => $post): 
                    ?>
                    <div class="blog-card" data-aos="fade-up" data-aos-delay="<?php echo min($index * 50, 300); ?>">
                        <div class="card-image">
                            <img src="<?php echo !empty($post['featured_image']) ? 'uploads/images/' . $post['featured_image'] : 'assets/images/default-post.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($post['title']); ?>"
                                 loading="lazy">
                            <span class="card-category"><?php echo htmlspecialchars($post['category'] ?? 'Uncategorized'); ?></span>
                            <?php if (isset($post['is_featured']) && $post['is_featured'] == 1): ?>
                            <span class="featured-tag">
                                <i class="fas fa-star"></i> Featured
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <div class="card-meta">
                                <span><i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($post['created_at'])); ?></span>
                                <span><i class="far fa-eye"></i> <?php echo number_format($post['views'] ?? 0); ?></span>
                                <span><i class="far fa-clock"></i> <?php echo ceil(str_word_count(strip_tags($post['content'])) / 200); ?> min</span>
                            </div>
                            <h3 class="card-title">
                                <a href="blog-post.php?slug=<?php echo $post['slug']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                            </h3>
                            <p class="card-excerpt"><?php echo htmlspecialchars(truncateText($post['excerpt'] ?: strip_tags($post['content']), 100)); ?></p>
                            <div class="card-footer">
                                <a href="blog-post.php?slug=<?php echo $post['slug']; ?>" class="read-link">
                                    Read More <i class="fas fa-arrow-right"></i>
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
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($category_filter) ? '&category=' . urlencode($category_filter) : ''; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <div class="no-posts" data-aos="fade-up">
                    <i class="fas fa-newspaper"></i>
                    <h3>No Posts Found</h3>
                    <p>Check back soon for new articles and stories.</p>
                    <?php if (!empty($search) || !empty($category_filter)): ?>
                    <a href="blog.php" class="read-more-btn" style="margin-top: 20px;">
                        <i class="fas fa-arrow-left"></i> Clear filters and view all posts
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <?php if ($blog_sidebar_enabled == '1'): ?>
            <aside class="blog-sidebar">
                <!-- Categories Widget -->
                <?php if (!empty($categories)): ?>
                <div class="sidebar-widget" data-aos="fade-up">
                    <h3><i class="fas fa-folder-open"></i> Categories</h3>
                    <ul class="category-list">
                        <li>
                            <a href="blog.php" class="<?php echo empty($category_filter) ? 'active' : ''; ?>">
                                <span>All Posts</span>
                                <span class="count"><?php echo $total_posts; ?></span>
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="blog.php?category=<?php echo urlencode($cat['name']); ?>" class="<?php echo ($category_filter == $cat['name']) ? 'active' : ''; ?>">
                                <span><?php echo htmlspecialchars($cat['name']); ?></span>
                                <span class="count"><?php echo $cat['count']; ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Recent Posts Widget -->
                <?php if ($blog_recent_posts_enabled == '1' && !empty($recent_posts)): ?>
                <div class="sidebar-widget" data-aos="fade-up" data-aos-delay="100">
                    <h3><i class="fas fa-clock"></i> Recent Posts</h3>
                    <?php foreach ($recent_posts as $recent): ?>
                    <div class="recent-post">
                        <img src="<?php echo !empty($recent['featured_image']) ? 'uploads/images/' . $recent['featured_image'] : 'assets/images/default-post.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($recent['title']); ?>" 
                             class="recent-post-image"
                             loading="lazy">
                        <div class="recent-post-content">
                            <div class="recent-post-title">
                                <a href="blog-post.php?slug=<?php echo $recent['slug']; ?>"><?php echo htmlspecialchars(truncateText($recent['title'], 50)); ?></a>
                            </div>
                            <div class="recent-post-date">
                                <i class="far fa-calendar"></i> <?php echo date('M d, Y', strtotime($recent['created_at'])); ?>
                                <span style="margin-left: 8px;"><i class="far fa-eye"></i> <?php echo number_format($recent['views']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </aside>
            <?php endif; ?>
        </div>
    </div>

    <!-- Newsletter Section -->
    <?php if ($blog_newsletter_enabled == '1'): ?>
    <section class="newsletter-section">
        <div class="newsletter-content" data-aos="fade-up">
            <h2><?php echo htmlspecialchars($blog_newsletter_title); ?></h2>
            <p><?php echo htmlspecialchars($blog_newsletter_subtitle); ?></p>
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
    <script>
        // Initialize AOS
        AOS.init({
            once: true,
            duration: 800,
            easing: 'ease-in-out',
            disable: window.innerWidth < 768
        });

        // Add loading skeleton while images load
        document.querySelectorAll('.blog-card img, .featured-image img, .recent-post-image').forEach(img => {
            if (!img.complete) {
                img.classList.add('skeleton');
                img.addEventListener('load', () => {
                    img.classList.remove('skeleton');
                });
                img.addEventListener('error', () => {
                    img.classList.remove('skeleton');
                });
            }
        });

        // Sticky category filter on scroll
        let lastScrollTop = 0;
        const categoryFilter = document.querySelector('.category-filter');
        
        if (categoryFilter) {
            window.addEventListener('scroll', function() {
                let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                if (scrollTop > 100) {
                    categoryFilter.style.top = '0';
                } else {
                    categoryFilter.style.top = '80px';
                }
                lastScrollTop = scrollTop;
            });
        }
    </script>
</body>
</html>