<?php
/**
 * Painlesslyf - Functions File
 * Contains all helper functions for the website
 */

// Prevent direct access
if (!defined('SITE_URL') && !defined('DB_HOST')) {
    die('Direct access not permitted');
}

/**
 * Get site setting with default value
 */
function getSetting($key, $default = '') {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return $default;
    }
    
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    if (!$stmt) {
        return $default;
    }
    
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    
    return $default;
}

/**
 * Update site setting
 */
function updateSetting($key, $value) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("sss", $key, $value, $value);
    return $stmt->execute();
}

/**
 * Get blog categories configured by an administrator and used by existing posts.
 */
function getBlogCategories() {
    global $conn;

    $categories = [];
    $configured = json_decode(getSetting('blog_categories', '[]'), true);
    if (is_array($configured)) {
        foreach ($configured as $category) {
            $category = trim((string) $category);
            if ($category !== '') {
                $categories[] = $category;
            }
        }
    }

    $result = $conn->query("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL AND category != ''");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = trim($row['category']);
        }
    }

    $categories = array_values(array_unique(array_filter($categories)));
    natcasesort($categories);
    return $categories;
}

/**
 * Get hero background style with opacity
 */
function getHeroBackgroundStyle() {
    $background = getSetting('hero_background_image', '');
    $opacity = getSetting('hero_background_opacity', '0.3');
    $overlay = getSetting('hero_background_overlay', 'rgba(0,0,0,0.5)');
    $textColor = getSetting('hero_text_color', '#ffffff');
    
    $style = "color: {$textColor}; position: relative;";
    
    if (!empty($background)) {
        // Check if file exists
        if (file_exists('../' . $background) || file_exists($background)) {
            $style .= " background: linear-gradient({$overlay}, {$overlay}), url('{$background}'); background-size: cover; background-position: center; background-attachment: fixed;";
        } else {
            // Default gradient if image doesn't exist
            $style .= " background: linear-gradient(135deg, #4a7c59 0%, #2c4a3b 100%);";
        }
    } else {
        // Default gradient if no background image
        $style .= " background: linear-gradient(135deg, #4a7c59 0%, #2c4a3b 100%);";
    }
    
    return $style;
}

/**
 * Get navbar style based on settings
 */
function getNavbarStyle() {
    $bgColor = getSetting('navbar_background', 'rgba(255,255,255,0.95)');
    $textColor = getSetting('navbar_text_color', '#333333');
    
    return "background: {$bgColor}; color: {$textColor};";
}

/**
 * Shared page hero / header wallpaper dimensions — matches blog.php
 */
function getPageHeroStyles() {
    if (defined('PAGE_HERO_STYLES_LOADED')) {
        return '';
    }
    define('PAGE_HERO_STYLES_LOADED', true);

    return '
        <style>
            .blog-header,
            .audio-header,
            .about-hero,
            .contact-header,
            .post-header {
                padding: 150px 0 80px;
                margin-top: -80px;
                position: relative;
                overflow: hidden;
                min-height: 0;
                height: auto;
            }

            .about-hero {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .blog-header-content,
            .audio-header-content,
            .about-hero-content,
            .contact-header-content,
            .post-header-content {
                position: relative;
                z-index: 2;
                text-align: center;
                max-width: 800px;
                margin: 0 auto;
                padding: 0 20px;
                width: 100%;
            }

            .blog-header .container,
            .audio-header .container,
            .about-hero .container,
            .contact-header .container {
                position: relative;
                z-index: 2;
            }

            .blog-header h1,
            .audio-header h1,
            .about-hero h1,
            .contact-header h1,
            .post-header h1 {
                font-family: "Playfair Display", serif;
                font-size: clamp(32px, 5vw, 56px);
                font-weight: 800;
                margin-bottom: 20px;
                text-shadow: 0 2px 12px rgba(0, 0, 0, 0.25);
                line-height: 1.15;
            }

            .blog-header p,
            .audio-header p,
            .about-hero p,
            .contact-header p {
                font-size: clamp(16px, 2.5vw, 20px);
                opacity: 0.95;
                margin-bottom: 30px;
                line-height: 1.5;
                max-width: 700px;
                margin-left: auto;
                margin-right: auto;
            }

            .blog-header::after,
            .audio-header::after,
            .about-hero::after,
            .contact-header::after,
            .post-header::after {
                content: "";
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, 0.3);
                z-index: 1;
                pointer-events: none;
            }

            @media (max-width: 768px) {
                .blog-header,
                .audio-header,
                .about-hero,
                .contact-header,
                .post-header {
                    padding: 120px 0 60px;
                    margin-top: -80px;
                }
            }
        </style>';
}

/**
 * Get shared footer styles (green gradient across all pages)
 */
function getFooterStyles() {
    $gradientStart = getSetting('footer_gradient_start', '#4a7c59');
    $gradientEnd = getSetting('footer_gradient_end', '#2c4a3b');
    $textColor = getSetting('footer_text_color', '#ffffff');
    $linkColor = getSetting('footer_link_color', 'rgba(255,255,255,0.82)');
    $linkHoverColor = getSetting('footer_link_hover_color', '#f6dfb3');
    $headingColor = getSetting('footer_heading_color', '#ffffff');
    $borderColor = getSetting('footer_border_color', 'rgba(255,255,255,0.18)');

    return "
        <style>
            .footer {
                background: linear-gradient(135deg, {$gradientStart} 0%, {$gradientEnd} 100%);
                color: {$textColor};
                padding: 0 0 24px;
                position: relative;
                overflow: hidden;
            }
            .footer::before {
                content: '';
                position: absolute;
                inset: 0;
                background: radial-gradient(circle at top left, rgba(255,255,255,0.12), transparent 38%);
                pointer-events: none;
            }
            .footer .container {
                position: relative;
                z-index: 1;
            }
            .footer-top {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 20px;
                padding: 30px 32px;
                margin: 0 0 28px;
                border: 1px solid rgba(255,255,255,0.14);
                border-radius: 24px;
                background: rgba(255,255,255,0.06);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
            }
            .footer-top .footer-kicker {
                display: block;
                font-size: 12px;
                letter-spacing: 0.18em;
                text-transform: uppercase;
                color: rgba(255,255,255,0.72);
                margin-bottom: 8px;
            }
            .footer-top h3 {
                margin: 0;
                font-size: clamp(20px, 2vw, 28px);
                font-weight: 700;
                color: {$headingColor};
            }
            .site-popup {
                position: fixed;
                inset: 0;
                background: rgba(15, 24, 36, 0.62);
                display: none;
                align-items: center;
                justify-content: center;
                padding: 24px;
                z-index: 9999;
            }
            .site-popup.show {
                display: flex;
            }
            .site-popup-card {
                position: relative;
                width: min(100%, 440px);
                background: #fff;
                border-radius: 22px;
                padding: 28px 24px 20px;
                box-shadow: 0 30px 80px rgba(15, 24, 36, 0.25);
                border: 1px solid rgba(17, 24, 39, 0.08);
            }
            .site-popup-card h3 {
                margin: 0 0 12px;
                font-size: 1.75rem;
                color: #1a2744;
            }
            .site-popup-card p {
                color: #5a6478;
                margin: 0 0 22px;
                font-size: 0.98rem;
                line-height: 1.6;
            }
            .site-popup-actions {
                display: flex;
                justify-content: flex-end;
                gap: 12px;
                flex-wrap: wrap;
            }
            .btn {
                appearance: none;
                border: none;
                border-radius: 999px;
                padding: 11px 20px;
                font-size: 0.95rem;
                font-weight: 700;
                cursor: pointer;
                transition: transform 0.2s ease, opacity 0.2s ease;
            }
            .btn:hover {
                transform: translateY(-1px);
            }
            .btn-primary {
                background: linear-gradient(135deg, #1a2744 0%, #2b3d66 100%);
                color: #fff;
            }
            .btn-secondary {
                background: #eef2f6;
                color: #1a2744;
            }
            .newsletter-close {
                position: absolute;
                top: 12px;
                right: 12px;
                border: none;
                background: transparent;
                color: #64748B;
                font-size: 1.8rem;
                cursor: pointer;
                line-height: 1;
            }
            .newsletter-form {
                display: flex;
                flex-direction: column;
                gap: 14px;
            }
            .newsletter-form input {
                width: 100%;
                border: 1px solid rgba(15, 24, 36, 0.12);
                border-radius: 12px;
                padding: 14px 16px;
                font-size: 1rem;
                color: #1a2744;
                background: #fff;
            }
            .newsletter-form input:focus {
                outline: 2px solid rgba(26, 39, 68, 0.14);
                border-color: rgba(26, 39, 68, 0.2);
            }
            .footer-cta {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 12px 22px;
                border-radius: 999px;
                text-decoration: none;
                background: linear-gradient(135deg, #f6dfb3 0%, #d9b46a 100%);
                color: #1a2744;
                font-weight: 700;
                box-shadow: 0 8px 24px rgba(214, 180, 106, 0.35);
                transition: transform 0.25s ease, box-shadow 0.25s ease;
                white-space: nowrap;
                border: none;
                cursor: pointer;
            }
            .footer-cta:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 30px rgba(214, 180, 106, 0.45);
            }
            .footer-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 28px;
                padding-top: 8px;
                margin-bottom: 30px;
            }
            @media (min-width: 576px) {
                .footer-grid { grid-template-columns: repeat(2, 1fr); }
            }
            @media (min-width: 992px) {
                .footer-grid { grid-template-columns: repeat(4, 1fr); gap: 36px; }
            }
            .footer-col {
                padding: 22px 18px;
                border: 1px solid rgba(255,255,255,0.09);
                border-radius: 18px;
                background: rgba(255,255,255,0.03);
            }
            .footer-logo img {
                width: 72px;
                height: 72px;
                object-fit: cover;
                border-radius: 50%;
                border: 3px solid rgba(255,255,255,0.9);
                box-shadow: 0 6px 18px rgba(0,0,0,0.18);
                margin-bottom: 16px;
                background: #fff;
            }
            .footer-description {
                color: {$linkColor};
                line-height: 1.7;
                margin-bottom: 20px;
                font-size: 14px;
            }
            .footer-social {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
            }
            .footer-social a {
                color: {$textColor};
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: rgba(255,255,255,0.12);
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
                text-decoration: none;
                border: 1px solid rgba(255,255,255,0.14);
            }
            .footer-social a:hover {
                background: rgba(255,255,255,0.22);
                transform: translateY(-2px);
                color: {$linkHoverColor};
            }
            .footer-col h3 {
                font-size: 18px;
                font-weight: 700;
                margin-bottom: 20px;
                color: {$headingColor};
            }
            .footer-links {
                list-style: none;
                padding: 0;
                margin: 0;
            }
            .footer-links li {
                margin-bottom: 12px;
                color: {$linkColor};
            }
            .footer-links a {
                color: {$linkColor};
                text-decoration: none;
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                transition: color 0.3s ease;
            }
            .footer-links a:hover {
                color: {$linkHoverColor};
            }
            .footer-links a i.fa-chevron-right {
                font-size: 10px;
                opacity: 0.7;
            }
            .footer-bottom {
                text-align: center;
                padding-top: 24px;
                border-top: 1px solid {$borderColor};
                color: {$linkColor};
                font-size: 14px;
            }
            .footer-bottom p {
                margin: 0 0 8px;
            }
            .footer-credit {
                margin: 0;
                font-size: 13px;
                opacity: 0.85;
            }
            .footer-credit strong {
                color: {$headingColor};
                font-weight: 700;
            }
            @media (max-width: 991px) {
                .footer {
                    padding-bottom: 20px;
                }
                .footer-top {
                    padding: 24px;
                    margin-bottom: 20px;
                }
                .footer-col {
                    padding: 20px 16px;
                }
            }
            @media (max-width: 575px) {
                .footer-top {
                    align-items: flex-start;
                    flex-direction: column;
                    gap: 16px;
                    padding: 22px 18px;
                    border-radius: 18px;
                }
                .footer-top h3 {
                    font-size: 22px;
                    line-height: 1.25;
                }
                .footer-cta {
                    width: 100%;
                    padding: 12px 16px;
                }
                .footer-grid {
                    gap: 14px;
                    margin-bottom: 22px;
                }
                .footer-col {
                    padding: 18px 16px;
                    border-radius: 14px;
                }
                .footer-col h3 {
                    margin-bottom: 14px;
                }
                .footer-bottom {
                    padding: 20px 8px 0;
                    font-size: 13px;
                }
                .footer-credit {
                    line-height: 1.5;
                }
            }
        </style>
    ";
}

/**
 * Get custom CSS
 */
function getCustomCSS() {
    $customCSS = getSetting('custom_css', '');
    $primaryColor = getSetting('primary_color', '#4a7c59');
    $secondaryColor = getSetting('secondary_color', '#2c4a3b');
    $fontFamily = getSetting('font_family', 'Inter, sans-serif');
    
    $css = "
        <style>
            :root {
                --primary-color: {$primaryColor};
                --secondary-color: {$secondaryColor};
                --font-family: {$fontFamily};
            }
            body {
                font-family: var(--font-family);
            }
            .btn-primary {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
            }
            .btn-primary:hover {
                background-color: var(--secondary-color);
                border-color: var(--secondary-color);
            }
            .priority-card .card-icon {
                color: var(--primary-color);
            }
            a:hover {
                color: var(--primary-color);
            }
            .pagination .page-item.active .page-link {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
            }
            {$customCSS}
        </style>
    ";
    
    return $css;
}

/**
 * Get custom JavaScript
 */
function getCustomJS() {
    $customJS = getSetting('custom_js', '');
    $enableAnimated = getSetting('enable_animated_background', '0');
    
    $js = "<script>";
    
    if ($enableAnimated == '1') {
        $js .= "
            // Animated background particles
            document.addEventListener('DOMContentLoaded', function() {
                const hero = document.querySelector('.hero');
                if (hero) {
                    hero.classList.add('animated-bg');
                }
            });
        ";
    }
    
    $js .= $customJS . "</script>";
    
    return $js;
}

/**
 * Check if admin is logged in
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Get recent blog posts
 */
function getRecentPosts($limit = 5) {
    global $conn;
    
    $posts = [];
    
    if (!isset($conn) || $conn->connect_error) {
        return $posts;
    }
    
    $stmt = $conn->prepare("SELECT * FROM posts WHERE status = 'published' ORDER BY created_at DESC LIMIT ?");
    if (!$stmt) {
        return $posts;
    }
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    
    return $posts;
}

/**
 * Get recent audio messages
 */
function getRecentAudio($limit = 5) {
    global $conn;
    
    $audio = [];
    
    if (!isset($conn) || $conn->connect_error) {
        return $audio;
    }
    
    $stmt = $conn->prepare("SELECT * FROM audio_messages WHERE status = 'published' ORDER BY created_at DESC LIMIT ?");
    if (!$stmt) {
        return $audio;
    }
    
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $audio[] = $row;
    }
    
    return $audio;
}

/**
 * Get city weather (mock function - replace with actual API if needed)
 */
function getCityWeather($city) {
    // This is a mock function - in production, you'd use a real weather API
    $temperatures = [
        'New York' => '18°C',
        'Dubai' => '32°C',
        'Amsterdam' => '15°C',
        'St. Tropez' => '24°C',
        'São Paulo' => '22°C',
        'London' => '14°C',
        'Cape Town' => '20°C',
        'Sydney' => '23°C'
    ];
    
    return $temperatures[$city] ?? '--°C';
}

/**
 * Truncate text to specified length
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $truncated = substr($text, 0, $length);
    $lastSpace = strrpos($truncated, ' ');
    
    if ($lastSpace !== false) {
        $truncated = substr($truncated, 0, $lastSpace);
    }
    
    return $truncated . '...';
}

/**
 * Format date
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Get post by slug
 */
function getPostBySlug($slug) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT * FROM posts WHERE slug = ? AND status = 'published'");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Get audio by ID
 */
function getAudioById($id) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT * FROM audio_messages WHERE id = ? AND status = 'published'");
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Increment post views
 */
function incrementPostViews($postId) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE posts SET views = views + 1 WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $postId);
    return $stmt->execute();
}

/**
 * Increment audio plays
 */
function incrementAudioPlays($audioId) {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $stmt = $conn->prepare("UPDATE audio_messages SET plays = plays + 1 WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $audioId);
    return $stmt->execute();
}

/**
 * Get all posts with pagination
 */
function getAllPosts($page = 1, $perPage = 10) {
    global $conn;
    
    $posts = [];
    $offset = ($page - 1) * $perPage;
    
    if (!isset($conn) || $conn->connect_error) {
        return ['posts' => $posts, 'total' => 0];
    }
    
    // Get total count
    $countResult = $conn->query("SELECT COUNT(*) as total FROM posts WHERE status = 'published'");
    $total = $countResult->fetch_assoc()['total'];
    
    // Get posts
    $stmt = $conn->prepare("SELECT * FROM posts WHERE status = 'published' ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) {
        return ['posts' => $posts, 'total' => $total];
    }
    
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    
    return ['posts' => $posts, 'total' => $total];
}

/**
 * Get all audio with pagination
 */
function getAllAudio($page = 1, $perPage = 10) {
    global $conn;
    
    $audio = [];
    $offset = ($page - 1) * $perPage;
    
    if (!isset($conn) || $conn->connect_error) {
        return ['audio' => $audio, 'total' => 0];
    }
    
    // Get total count
    $countResult = $conn->query("SELECT COUNT(*) as total FROM audio_messages WHERE status = 'published'");
    $total = $countResult->fetch_assoc()['total'];
    
    // Get audio
    $stmt = $conn->prepare("SELECT * FROM audio_messages WHERE status = 'published' ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) {
        return ['audio' => $audio, 'total' => $total];
    }
    
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $audio[] = $row;
    }
    
    return ['audio' => $audio, 'total' => $total];
}

/**
 * Generate slug from string
 */
function createSlug($string) {
    // Replace non-alphanumeric characters with hyphens
    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($string)));
    // Remove multiple hyphens
    $slug = preg_replace('/-+/', '-', $slug);
    // Trim hyphens from ends
    $slug = trim($slug, '-');
    
    return $slug;
}

/**
 * Upload file with validation
 */
function uploadFile($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp3', 'mp4']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check if file type is allowed
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Generate unique filename
    $fileName = time() . '_' . uniqid() . '.' . $extension;
    $targetPath = $targetDir . $fileName;
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return [
            'success' => true,
            'fileName' => $fileName,
            'filePath' => $targetPath,
            'message' => 'File uploaded successfully'
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate pagination HTML
 */
function generatePagination($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Send email (basic function - enhance with PHPMailer for production)
 */
function sendEmail($to, $subject, $message, $from = '') {
    $headers = 'From: ' . ($from ?: getSetting('contact_email', 'noreply@painlesslyf.com')) . "\r\n" .
               'Reply-To: ' . ($from ?: getSetting('contact_email', 'noreply@painlesslyf.com')) . "\r\n" .
               'X-Mailer: PHP/' . phpversion() . "\r\n" .
               'MIME-Version: 1.0' . "\r\n" .
               'Content-Type: text/html; charset=UTF-8';
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Get social media links
 */
function getSocialLinks() {
    return [
        'instagram' => getSetting('instagram_url', '#'),
        'twitter' => getSetting('twitter_url', '#'),
        'youtube' => getSetting('youtube_url', '#'),
        'spotify' => getSetting('spotify_url', '#'),
        'facebook' => getSetting('facebook_url', '#'),
        'linkedin' => getSetting('linkedin_url', '#')
    ];
}

/**
 * Check if string is JSON
 */
function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

/**
 * Get file size in readable format
 */
function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Generate random string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Get client IP address
 */
function getClientIP() {
    $ipaddress = '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_X_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    } else if (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    } else if (isset($_SERVER['HTTP_FORWARDED'])) {
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    } else if (isset($_SERVER['REMOTE_ADDR'])) {
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    } else {
        $ipaddress = 'UNKNOWN';
    }
    
    return $ipaddress;
}

/**
 * Log admin action - FIXED VERSION with proper NULL handling
 */
function logAdminAction($action, $details = '') {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        error_log("logAdminAction: No database connection");
        return false;
    }
    
    // First, ensure the admin_logs table exists
    ensureLogsTableExists();
    
    // For failed logins or when no admin is logged in, admin_id should be NULL, not 0
    $admin_id = null;
    
    // Only set admin_id if user is actually logged in
    if (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0) {
        $admin_id = $_SESSION['admin_id'];
    }
    
    $ip = getClientIP();
    
    // Use a try-catch block to handle any database errors
    try {
        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$stmt) {
            error_log("logAdminAction: Failed to prepare statement - " . $conn->error);
            return false;
        }
        
        // Bind parameters - admin_id can be null
        $stmt->bind_param("isss", $admin_id, $action, $details, $ip);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("logAdminAction: Failed to execute - " . $stmt->error);
        }
        
        $stmt->close();
        return $result;
    } catch (Exception $e) {
        error_log("logAdminAction: Exception - " . $e->getMessage());
        return false;
    }
}

/**
 * Get website statistics
 */
function getSiteStats() {
    global $conn;
    
    $stats = [
        'total_posts' => 0,
        'total_audio' => 0,
        'total_views' => 0,
        'total_plays' => 0
    ];
    
    if (!isset($conn) || $conn->connect_error) {
        return $stats;
    }
    
    // Get total posts
    $result = $conn->query("SELECT COUNT(*) as count FROM posts WHERE status = 'published'");
    if ($result) {
        $stats['total_posts'] = $result->fetch_assoc()['count'];
    }
    
    // Get total audio
    $result = $conn->query("SELECT COUNT(*) as count FROM audio_messages WHERE status = 'published'");
    if ($result) {
        $stats['total_audio'] = $result->fetch_assoc()['count'];
    }
    
    // Get total views
    $result = $conn->query("SELECT SUM(views) as total FROM posts");
    if ($result) {
        $stats['total_views'] = $result->fetch_assoc()['total'] ?? 0;
    }
    
    // Get total plays
    $result = $conn->query("SELECT SUM(plays) as total FROM audio_messages");
    if ($result) {
        $stats['total_plays'] = $result->fetch_assoc()['total'] ?? 0;
    }
    
    return $stats;
}

/**
 * Create backup of settings
 */
function backupSettings() {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    $backup = [
        'timestamp' => date('Y-m-d H:i:s'),
        'settings' => $settings
    ];
    
    $backupDir = '../backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    
    $backupFile = $backupDir . 'settings_backup_' . date('Ymd_His') . '.json';
    file_put_contents($backupFile, json_encode($backup, JSON_PRETTY_PRINT));
    
    return $backupFile;
}

/**
 * Restore settings from backup
 */
function restoreSettings($backupFile) {
    global $conn;
    
    if (!file_exists($backupFile)) {
        return false;
    }
    
    $backup = json_decode(file_get_contents($backupFile), true);
    
    if (!isset($backup['settings'])) {
        return false;
    }
    
    foreach ($backup['settings'] as $key => $value) {
        updateSetting($key, $value);
    }
    
    return true;
}

/**
 * Clear cache
 */
function clearCache() {
    $cacheDir = '../cache/';
    
    if (file_exists($cacheDir)) {
        $files = glob($cacheDir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
    
    return true;
}

/**
 * Check if a function is called from admin area
 */
function isAdminArea() {
    return strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
}

/**
 * Get current URL
 */
function getCurrentURL() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Redirect to URL
 */
function redirect($url) {
    header('Location: ' . $url);
    exit();
}

/**
 * Display flash message
 */
function flashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = ['message' => $message, 'type' => $type];
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Create all required tables if they don't exist
 */
function createRequiredTables() {
    global $conn;
    
    $tables_created = [];
    
    if (!isset($conn) || $conn->connect_error) {
        error_log("createRequiredTables: No database connection");
        return $tables_created;
    }
    
    // Check if admin_logs table exists
    $result = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($result->num_rows == 0) {
        // Create admin_logs table WITHOUT foreign key constraint to avoid issues
        $sql = "CREATE TABLE IF NOT EXISTS `admin_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `admin_id` int(11) DEFAULT NULL,
            `action` varchar(100) NOT NULL,
            `details` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `admin_id` (`admin_id`),
            KEY `action` (`action`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        if ($conn->query($sql)) {
            $tables_created[] = 'admin_logs';
            error_log("createRequiredTables: Created admin_logs table");
        } else {
            error_log("createRequiredTables: Failed to create admin_logs table - " . $conn->error);
        }
    }
    
    return $tables_created;
}

/**
 * Ensure admin_logs table exists - SIMPLIFIED VERSION without foreign key
 */
function ensureLogsTableExists() {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    $result = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($result->num_rows == 0) {
        // Create table WITHOUT foreign key constraint
        $sql = "CREATE TABLE IF NOT EXISTS `admin_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `admin_id` int(11) DEFAULT NULL,
            `action` varchar(100) NOT NULL,
            `details` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `admin_id` (`admin_id`),
            KEY `action` (`action`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        return $conn->query($sql);
    }
    
    return true;
}

/**
 * Fix admin_logs table - remove foreign key constraint if it exists
 */
function fixAdminLogsTable() {
    global $conn;
    
    if (!isset($conn) || $conn->connect_error) {
        return false;
    }
    
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($result->num_rows == 0) {
        // Create it without foreign key
        return ensureLogsTableExists();
    }
    
    // Try to drop foreign key constraint if it exists
    try {
        $conn->query("ALTER TABLE `admin_logs` DROP FOREIGN KEY IF EXISTS `admin_logs_ibfk_1`");
        error_log("fixAdminLogsTable: Dropped foreign key constraint");
    } catch (Exception $e) {
        // Constraint might not exist, ignore error
        error_log("fixAdminLogsTable: " . $e->getMessage());
    }
    
    return true;
}
?>