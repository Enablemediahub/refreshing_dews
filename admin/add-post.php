<?php
/**
 * Add/Edit Post - Admin Panel
 * Mobile responsive, with image upload, content editing, and post management
 * Includes featured post selection functionality
 * Now with TinyMCE Rich Text Editor with full font controls
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

// Get post ID for editing (if any)
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($post_id > 0);

// Initialize variables
$title = '';
$slug = '';
$content = '';
$excerpt = '';
$category = '';
$featured_image = '';
$status = 'draft';
$is_featured = 0;
$error = '';
$success = '';
$upload_error = '';

// Define safeCompressContent function if it doesn't exist
if (!function_exists('safeCompressContent')) {
    function safeCompressContent($content) {
        if (empty($content)) {
            return '';
        }
        try {
            $compressed = @gzcompress($content, 9);
            if ($compressed !== false) {
                return base64_encode($compressed);
            }
        } catch (Exception $e) {
            error_log("Compression failed: " . $e->getMessage());
        }
        return $content;
    }
}

// Define safeDecompressContent function if it doesn't exist
if (!function_exists('safeDecompressContent')) {
    function safeDecompressContent($content) {
        if (empty($content)) {
            return '';
        }
        $decoded = base64_decode($content, true);
        if ($decoded !== false) {
            try {
                $decompressed = @gzuncompress($decoded);
                if ($decompressed !== false) {
                    return $decompressed;
                }
            } catch (Exception $e) {
                error_log("Decompression failed: " . $e->getMessage());
            }
        }
        return $content;
    }
}

// Define handleImageUpload function
if (!function_exists('handleImageUpload')) {
    function handleImageUpload($file, $subfolder = 'posts') {
        $target_dir = "../uploads/images/";
        
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
            return ['success' => true, 'filename' => $new_filename, 'filepath' => 'uploads/images/' . $new_filename];
        } else {
            return ['success' => false, 'error' => 'Sorry, there was an error uploading your file.'];
        }
    }
}

// Define handleImageUploadForEditor function (returns URL for editor)
if (!function_exists('handleImageUploadForEditor')) {
    function handleImageUploadForEditor($file) {
        $result = handleImageUpload($file);
        if ($result['success']) {
            return ['success' => true, 'location' => '../' . $result['filepath']];
        }
        return $result;
    }
}

// Helper function to log admin actions if not exists
if (!function_exists('logAdminAction')) {
    function logAdminAction($action, $details = '') {
        global $conn;
        $admin_id = $_SESSION['admin_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $admin_id, $action, $details, $ip);
            $stmt->execute();
        }
    }
}

// Handle AJAX image upload from TinyMCE
if (isset($_POST['tinymce_image_upload']) && isset($_FILES['image'])) {
    header('Content-Type: application/json');
    $result = handleImageUploadForEditor($_FILES['image']);
    if ($result['success']) {
        echo json_encode(['success' => true, 'location' => $result['location']]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    exit();
}

// Get configured categories and categories already used by posts for dropdown
$categories = getBlogCategories();

// If editing, load existing post data
if ($is_edit) {
    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Location: posts.php');
        exit();
    }
    
    $post = $result->fetch_assoc();
    $title = $post['title'];
    $slug = $post['slug'];
    $content = safeDecompressContent($post['content']);
    $excerpt = $post['excerpt'] ?? '';
    $category = $post['category'] ?? '';
    $featured_image = $post['featured_image'] ?? '';
    $status = $post['status'];
    $is_featured = $post['is_featured'] ?? 0;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Sanitize and validate inputs
    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $status = in_array($status, ['draft', 'published'], true) ? $status : 'draft';
    $content = $_POST['content'] ?? '';
    $is_featured = isset($_POST['is_featured']) ? (int)$_POST['is_featured'] : 0;
    
    // Generate slug from title if not provided or if editing and title changed
    $slug = trim($_POST['slug'] ?? '');
    if (empty($slug)) {
        $slug = createSlug($title);
    } else {
        $slug = createSlug($slug);
    }
    
    // Validate required fields
    if (empty($title)) {
        $error = 'Please enter a post title.';
    } elseif (empty($content)) {
        $error = 'Please enter post content.';
    } else {
        // Check if slug already exists (for new posts or if slug changed)
        $slug_check_sql = "SELECT id FROM posts WHERE slug = ?";
        if ($is_edit) {
            $slug_check_sql .= " AND id != ?";
        }
        
        $stmt = $conn->prepare($slug_check_sql);
        if ($is_edit) {
            $stmt->bind_param("si", $slug, $post_id);
        } else {
            $stmt->bind_param("s", $slug);
        }
        $stmt->execute();
        $slug_result = $stmt->get_result();
        
        if ($slug_result->num_rows > 0) {
            $error = 'A post with this slug already exists. Please use a different title or slug.';
        } else {
            // Handle featured image upload
            $featured_image_path = $featured_image; // Keep existing if no new upload
            
            // Check if image removal is requested
            $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';
            
            if ($remove_image && $is_edit && !empty($featured_image)) {
                // Delete existing image file
                if (file_exists('../uploads/images/' . $featured_image)) {
                    unlink('../uploads/images/' . $featured_image);
                }
                $featured_image_path = '';
            }
            
            if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = handleImageUpload($_FILES['featured_image'], 'posts');
                if ($upload_result['success']) {
                    // Delete old image if exists
                    if ($is_edit && !empty($featured_image) && file_exists('../uploads/images/' . $featured_image)) {
                        unlink('../uploads/images/' . $featured_image);
                    }
                    $featured_image_path = $upload_result['filename'];
                } else {
                    $upload_error = $upload_result['error'];
                }
            }
            
            // Compress content for storage
            $compressed_content = safeCompressContent($content);
            
            // Handle featured post logic - only one post can be featured
            if ($is_featured == 1) {
                // Remove featured flag from all other posts
                $conn->query("UPDATE posts SET is_featured = 0 WHERE is_featured = 1");
            }
            
            if ($is_edit) {
                // Update existing post
                $update_sql = "UPDATE posts SET 
                                title = ?, 
                                slug = ?, 
                                content = ?, 
                                excerpt = ?, 
                                category = ?, 
                                featured_image = ?, 
                                status = ?,
                                is_featured = ?,
                                updated_at = NOW()
                              WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sssssssii", $title, $slug, $compressed_content, $excerpt, $category, $featured_image_path, $status, $is_featured, $post_id);
                
                if ($stmt->execute()) {
                    $success = 'Post updated successfully!';
                    logAdminAction('update_post', "Updated post: $title");
                    
                    // Reload post data
                    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
                    $stmt->bind_param("i", $post_id);
                    $stmt->execute();
                    $post = $stmt->get_result()->fetch_assoc();
                    $content = safeDecompressContent($post['content']);
                    $featured_image = $post['featured_image'];
                    $is_featured = $post['is_featured'];
                } else {
                    $error = 'Failed to update post. Please try again.';
                }
            } else {
                // Insert new post
                $insert_sql = "INSERT INTO posts (title, slug, content, excerpt, category, featured_image, author_id, status, is_featured, views, created_at, updated_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("ssssssisi", $title, $slug, $compressed_content, $excerpt, $category, $featured_image_path, $admin_id, $status, $is_featured);
                
                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    $success = 'Post created successfully!';
                    logAdminAction('add_post', "Added new post: $title");
                    
                    // Redirect to edit mode to avoid resubmission
                    header("Location: add-post.php?id=$new_id&success=1");
                    exit();
                } else {
                    $error = 'Failed to create post. Please try again.';
                }
            }
        }
    }
}

// Check for success query param after redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = 'Post created successfully!';
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
    <title><?php echo $is_edit ? 'Edit Post' : 'Add New Post'; ?> - Admin Panel</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <!-- TinyMCE Rich Text Editor with API Key -->
    <script src="https://cdn.tiny.cloud/1/537z2hru20djc65c6mv3bm8aptvi6ga8hgqymdxdr592ir6k/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    
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
        
        /* Admin Layout */
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
        
        /* Mobile Menu Toggle */
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
        
        /* Main Content */
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
        
        /* Top Navigation */
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
        
        /* Form Card */
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
        
        /* Form Elements */
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
            min-height: 100px;
        }
        
        /* Image Upload */
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
            margin-bottom: 8px;
        }
        
        .image-upload-area small {
            color: #adb5bd;
            font-size: 11px;
        }
        
        .current-image {
            margin-top: 15px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .current-image img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .current-image .image-info {
            flex: 1;
        }
        
        .current-image .image-info p {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .remove-image {
            color: #dc3545;
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .remove-image:hover {
            background: rgba(220, 53, 69, 0.1);
        }
        
        /* Row Layout */
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
        
        /* Slug Preview */
        .slug-preview {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 13px;
            color: <?php echo $primary_color; ?>;
            margin-top: 8px;
            word-break: break-all;
        }
        
        .slug-preview i {
            margin-right: 5px;
        }
        
        /* Status Toggle */
        .status-toggle {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .status-option {
            flex: 1;
            min-width: 100px;
        }
        
        .status-option input {
            display: none;
        }
        
        .status-option label {
            display: block;
            padding: 12px;
            text-align: center;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0;
            font-weight: 500;
        }
        
        .status-option input:checked + label {
            background: <?php echo $primary_color; ?>;
            border-color: <?php echo $primary_color; ?>;
            color: white;
        }
        
        /* Featured Post Styles */
        .featured-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 12px;
            padding: 12px 15px;
            margin-top: 10px;
            font-size: 12px;
            color: #856404;
        }
        
        .featured-warning i {
            margin-right: 8px;
        }
        
        /* Action Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
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
        
        .btn-outline {
            background: transparent;
            border: 2px solid #e9ecef;
            color: #6c757d;
        }
        
        .btn-outline:hover,
        .btn-outline:active {
            border-color: <?php echo $primary_color; ?>;
            color: <?php echo $primary_color; ?>;
        }
        
        /* Alerts */
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
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        /* TinyMCE Customization */
        .tox-tinymce {
            border-radius: 12px !important;
            border: 2px solid #e9ecef !important;
        }
        
        .tox-editor-header {
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
        }
        
        /* Loading */
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
        
        /* Touch-friendly */
        @media (hover: none) and (pointer: coarse) {
            .btn:active,
            .status-option label:active,
            .sidebar-menu-item:active {
                transform: scale(0.98);
            }
            
            .btn-primary:active,
            .btn-secondary:active {
                transform: scale(0.98);
            }
        }
        
        /* Print */
        @media print {
            .admin-sidebar,
            .mobile-menu-toggle,
            .sidebar-overlay,
            .form-actions {
                display: none;
            }
            .admin-main {
                padding: 0;
            }
            .form-card {
                box-shadow: none;
            }
        }
        
        /* Editor container responsive */
        .editor-container {
            width: 100%;
            overflow-x: auto;
        }
        
        /* Fix for iPad paste issues */
        .tox-edit-area {
            -webkit-user-select: text !important;
            user-select: text !important;
        }
        
        .tox-edit-area iframe {
            pointer-events: auto !important;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="admin-wrapper">
        <?php $current_page = basename(__FILE__); include __DIR__ . '/includes/sidebar.php'; ?>
        <!-- Main Content -->
        <div class="admin-main">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="top-nav-title">
                    <h1><?php echo $is_edit ? 'Edit Post' : 'Add New Post'; ?></h1>
                    <p><?php echo $is_edit ? 'Update your existing blog post' : 'Create a new blog post with images and content'; ?></p>
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
            
            <!-- Alerts -->
            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($upload_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-image"></i>
                <?php echo htmlspecialchars($upload_error); ?>
            </div>
            <?php endif; ?>
            
            <!-- Post Form -->
            <form method="POST" action="" enctype="multipart/form-data" id="postForm">
                <input type="hidden" name="action" value="save_post">
                
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-info-circle"></i> Post Information</h2>
                    </div>
                    <div class="form-body">
                        <div class="form-group">
                            <label for="title">Post Title <span class="required">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($title); ?>" 
                                   placeholder="Enter post title" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="slug">Slug (URL)</label>
                                <input type="text" class="form-control" id="slug" name="slug" 
                                       value="<?php echo htmlspecialchars($slug); ?>" 
                                       placeholder="auto-generated from title">
                                <div class="slug-preview">
                                    <i class="fas fa-link"></i> 
                                    Preview: <?php echo SITE_URL; ?>/blog-post.php?slug=<span id="slugPreview"><?php echo htmlspecialchars($slug ?: 'post-slug'); ?></span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <input type="text" class="form-control" id="category" name="category" 
                                       list="categoryList" 
                                       value="<?php echo htmlspecialchars($category); ?>" 
                                       placeholder="e.g., Life, Thoughts, Inspiration">
                                <datalist id="categoryList">
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php endforeach; ?>
                                    <option value="Life">
                                    <option value="Thoughts">
                                    <option value="Experiences">
                                    <option value="Inspiration">
                                    <option value="Growth">
                                    <option value="Reflections">
                                </datalist>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="is_featured">Featured Post</label>
                                <div class="status-toggle">
                                    <div class="status-option">
                                        <input type="radio" id="featured_no" name="is_featured" value="0" <?php echo $is_featured == 0 ? 'checked' : ''; ?>>
                                        <label for="featured_no">
                                            <i class="fas fa-star-o"></i> Not Featured
                                        </label>
                                    </div>
                                    <div class="status-option">
                                        <input type="radio" id="featured_yes" name="is_featured" value="1" <?php echo $is_featured == 1 ? 'checked' : ''; ?>>
                                        <label for="featured_yes" style="background: <?php echo $is_featured == 1 ? '#ffc107' : ''; ?>; color: <?php echo $is_featured == 1 ? '#333' : ''; ?>">
                                            <i class="fas fa-star"></i> Featured Post
                                        </label>
                                    </div>
                                </div>
                                <?php if ($is_featured == 1): ?>
                                <div class="featured-warning">
                                    <i class="fas fa-info-circle"></i> 
                                    This post is currently featured on the homepage. Only one post can be featured at a time.
                                </div>
                                <?php else: ?>
                                <div class="featured-warning" style="background: #e7f3ff; border-color: <?php echo $primary_color; ?>; color: <?php echo $primary_color; ?>;">
                                    <i class="fas fa-star"></i> 
                                    Setting this as featured will remove the featured status from any other post.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="featured_image">Featured Image</label>
                                <div class="image-upload-area" id="imageUploadArea">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Click to upload or drag and drop</p>
                                    <small>JPG, PNG, GIF, WEBP up to 5MB</small>
                                    <input type="file" id="featured_image" name="featured_image" accept="image/*" style="display: none;">
                                </div>
                                
                                <?php if ($is_edit && !empty($featured_image)): ?>
                                <div class="current-image" id="currentImage">
                                    <img src="../uploads/images/<?php echo htmlspecialchars($featured_image); ?>" alt="Current featured image">
                                    <div class="image-info">
                                        <p>Current image: <?php echo htmlspecialchars($featured_image); ?></p>
                                        <small>Upload a new image to replace it</small>
                                    </div>
                                    <button type="button" class="remove-image" id="removeImageBtn" title="Remove image">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="excerpt">Excerpt (Short Description)</label>
                                <textarea class="form-control" id="excerpt" name="excerpt" rows="5" 
                                          placeholder="A brief summary of your post (optional)"><?php echo htmlspecialchars($excerpt); ?></textarea>
                                <small style="color: #6c757d; display: block; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> This will appear in blog listings and SEO meta description.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Content Editor with TinyMCE -->
                <div class="form-card">
                    <div class="form-header">
                        <h2><i class="fas fa-edit"></i> Post Content</h2>
                        <p style="font-size: 12px; color: #6c757d; margin-top: 5px;">
                            <i class="fas fa-font"></i> You can change fonts, colors, sizes, and more using the toolbar
                        </p>
                    </div>
                    <div class="form-body">
                        <div class="form-group">
                            <label for="content">Content <span class="required">*</span></label>
                            <textarea id="content" name="content"><?php echo htmlspecialchars($content); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="form-card">
                    <div class="form-body">
                        <div class="form-group">
                            <label for="status">Post Status</label>
                            <div class="status-toggle">
                                <div class="status-option">
                                    <input type="radio" id="status_draft" name="status" value="draft" <?php echo $status === 'draft' ? 'checked' : ''; ?> required>
                                    <label for="status_draft">
                                        <i class="fas fa-pencil-alt"></i> Draft
                                    </label>
                                </div>
                                <div class="status-option">
                                    <input type="radio" id="status_published" name="status" value="published" <?php echo $status === 'published' ? 'checked' : ''; ?>>
                                    <label for="status_published">
                                        <i class="fas fa-globe"></i> Publish
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update Post' : 'Publish Post'; ?>
                    </button>
                    <a href="posts.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <?php if ($is_edit && $status === 'published'): ?>
                    <a href="../blog-post.php?slug=<?php echo urlencode($slug); ?>" target="_blank" class="btn btn-outline">
                        <i class="fas fa-eye"></i> View Post
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // TinyMCE Configuration with Full Font Controls
        tinymce.init({
            selector: '#content',
            height: 600,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
                'codesample', 'pagebreak', 'nonbreaking', 'save', 'directionality',
                'importcss', 'visualchars', 'noneditable', 'template'
            ],
            toolbar: 'undo redo | blocks | fontfamily fontsize | ' +
                'bold italic underline strikethrough | forecolor backcolor | ' +
                'alignleft aligncenter alignright alignjustify | ' +
                'bullist numlist outdent indent | ' +
                'removeformat | link image media | ' +
                'table | codesample | fullscreen | help',
            toolbar_mode: 'sliding',
            contextmenu: 'link image table',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 16px; line-height: 1.6; }',
            
            // Font Family Options
            font_family_formats: 'Arial=arial,helvetica,sans-serif;' +
                'Arial Black=arial black,avant garde;' +
                'Book Antiqua=book antiqua,palatino;' +
                'Comic Sans MS=comic sans ms,sans-serif;' +
                'Courier New=courier new,courier;' +
                'Georgia=georgia,palatino;' +
                'Helvetica=helvetica;' +
                'Impact=impact,chicago;' +
                'Inter=Inter, sans-serif;' +
                'Roboto=Roboto, sans-serif;' +
                'Tahoma=tahoma,arial,helvetica,sans-serif;' +
                'Terminal=terminal,monaco;' +
                'Times New Roman=times new roman,times;' +
                'Trebuchet MS=trebuchet ms,geneva;' +
                'Verdana=verdana,geneva;' +
                'Open Sans=Open Sans, sans-serif;' +
                'Lato=Lato, sans-serif;' +
                'Montserrat=Montserrat, sans-serif;' +
                'Poppins=Poppins, sans-serif;' +
                'Merriweather=Merriweather, serif;' +
                'Playfair Display=Playfair Display, serif;' +
                'Source Code Pro=Source Code Pro, monospace;',
            
            // Font Size Options
            font_size_formats: '8pt 10pt 12pt 14pt 16pt 18pt 20pt 24pt 28pt 32pt 36pt 40pt 48pt 56pt 64pt 72pt',
            
            // Enhanced paste handling for iPad
            paste_data_images: true,
            paste_as_text: false,
            paste_auto_cleanup_on_paste: true,
            paste_remove_styles: false,
            paste_remove_styles_if_webkit: false,
            paste_merge_formats: true,
            
            // Image upload handler
            images_upload_handler: function(blobInfo, progress) {
                return new Promise(function(resolve, reject) {
                    const xhr = new XMLHttpRequest();
                    const formData = new FormData();
                    
                    formData.append('tinymce_image_upload', '1');
                    formData.append('image', blobInfo.blob(), blobInfo.filename());
                    
                    xhr.open('POST', window.location.href, true);
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success && response.location) {
                                    resolve(response.location);
                                } else {
                                    reject('Upload failed: ' + (response.error || 'Unknown error'));
                                }
                            } catch(e) {
                                reject('Error parsing response');
                            }
                        } else {
                            reject('HTTP Error: ' + xhr.status);
                        }
                    };
                    xhr.onerror = function() {
                        reject('Upload failed');
                    };
                    xhr.send(formData);
                });
            },
            
            // File picker for images
            file_picker_types: 'image',
            file_picker_callback: function(callback, value, meta) {
                if (meta.filetype === 'image') {
                    const input = document.createElement('input');
                    input.setAttribute('type', 'file');
                    input.setAttribute('accept', 'image/*');
                    input.onchange = function() {
                        const file = this.files[0];
                        const formData = new FormData();
                        formData.append('tinymce_image_upload', '1');
                        formData.append('image', file);
                        
                        const xhr = new XMLHttpRequest();
                        xhr.open('POST', window.location.href, true);
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                const response = JSON.parse(xhr.responseText);
                                if (response.success && response.location) {
                                    callback(response.location);
                                } else {
                                    alert('Upload failed: ' + (response.error || 'Unknown error'));
                                }
                            }
                        };
                        xhr.send(formData);
                    };
                    input.click();
                }
            },
            
            // Enable drag and drop
            drag_drop: true,
            
            // Better handling of pasted content from Word/Web
            importcss_append: true,
            
            // Setup for iPad compatibility
            setup: function(editor) {
                // Fix for iPad paste issues - ensure contenteditable is properly set
                editor.on('init', function() {
                    const iframe = document.querySelector('#content_ifr');
                    if (iframe) {
                        iframe.style.pointerEvents = 'auto';
                        const doc = iframe.contentDocument || iframe.contentWindow.document;
                        if (doc) {
                            doc.body.setAttribute('contenteditable', 'true');
                            // Handle paste events to ensure images are properly processed
                            doc.addEventListener('paste', function(e) {
                                const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                                for (let i = 0; i < items.length; i++) {
                                    if (items[i].type.indexOf('image') !== -1) {
                                        e.preventDefault();
                                        const blob = items[i].getAsFile();
                                        const formData = new FormData();
                                        formData.append('tinymce_image_upload', '1');
                                        formData.append('image', blob);
                                        
                                        const xhr = new XMLHttpRequest();
                                        xhr.open('POST', window.location.href, true);
                                        xhr.onload = function() {
                                            if (xhr.status === 200) {
                                                const response = JSON.parse(xhr.responseText);
                                                if (response.success && response.location) {
                                                    editor.insertContent('<img src="' + response.location + '" alt="Pasted image" style="max-width:100%;">');
                                                }
                                            }
                                        };
                                        xhr.send(formData);
                                        break;
                                    }
                                }
                            });
                        }
                    }
                });
                
                // Log editor ready for debugging
                editor.on('ready', function() {
                    console.log('TinyMCE editor is ready with font controls');
                });
            }
        });
        
        // Generate slug from title automatically
        const titleInput = document.getElementById('title');
        const slugInput = document.getElementById('slug');
        const slugPreview = document.getElementById('slugPreview');
        
        function createSlugFromText(text) {
            return text.toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/--+/g, '-')
                .trim('-');
        }
        
        function updateSlugPreview() {
            let slugValue = slugInput.value.trim();
            if (!slugValue && titleInput.value.trim()) {
                slugValue = createSlugFromText(titleInput.value);
                slugInput.value = slugValue;
            }
            slugPreview.textContent = slugValue || 'post-slug';
        }
        
        titleInput.addEventListener('blur', function() {
            if (!slugInput.value.trim()) {
                slugInput.value = createSlugFromText(this.value);
                updateSlugPreview();
            }
        });
        
        slugInput.addEventListener('input', updateSlugPreview);
        updateSlugPreview();
        
        // Featured post warning update
        const featuredYes = document.getElementById('featured_yes');
        const featuredNo = document.getElementById('featured_no');
        const featuredWarning = document.querySelector('.featured-warning');
        
        if (featuredYes && featuredNo) {
            featuredYes.addEventListener('change', function() {
                if (this.checked) {
                    if (featuredWarning) {
                        featuredWarning.innerHTML = '<i class="fas fa-info-circle"></i> This post will be featured on the homepage. Any other featured post will be automatically unfeatured.';
                        featuredWarning.style.background = '#fff3cd';
                        featuredWarning.style.borderColor = '#ffc107';
                        featuredWarning.style.color = '#856404';
                    }
                }
            });
            
            featuredNo.addEventListener('change', function() {
                if (this.checked) {
                    if (featuredWarning) {
                        featuredWarning.innerHTML = '<i class="fas fa-star"></i> Setting this as featured will remove the featured status from any other post.';
                        featuredWarning.style.background = '#e7f3ff';
                        featuredWarning.style.borderColor = '<?php echo $primary_color; ?>';
                        featuredWarning.style.color = '<?php echo $primary_color; ?>';
                    }
                }
            });
        }
        
        // Image Upload Handling for featured image
        const imageUploadArea = document.getElementById('imageUploadArea');
        const imageInput = document.getElementById('featured_image');
        
        if (imageUploadArea) {
            imageUploadArea.addEventListener('click', () => imageInput.click());
            imageUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                imageUploadArea.style.borderColor = '<?php echo $primary_color; ?>';
                imageUploadArea.style.background = 'rgba(74, 124, 89, 0.05)';
            });
            imageUploadArea.addEventListener('dragleave', () => {
                imageUploadArea.style.borderColor = '#e9ecef';
                imageUploadArea.style.background = '#fafbfc';
            });
            imageUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                imageUploadArea.style.borderColor = '#e9ecef';
                imageUploadArea.style.background = '#fafbfc';
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    imageInput.files = files;
                    showImagePreview(files[0]);
                }
            });
        }
        
        if (imageInput) {
            imageInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    showImagePreview(this.files[0]);
                }
            });
        }
        
        function showImagePreview(file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const currentImageDiv = document.getElementById('currentImage');
                if (currentImageDiv) {
                    const img = currentImageDiv.querySelector('img');
                    if (img) img.src = e.target.result;
                } else {
                    // Create new preview
                    const existingPreview = document.getElementById('currentImage');
                    if (existingPreview) existingPreview.remove();
                    
                    const previewHtml = `
                        <div class="current-image" id="currentImage">
                            <img src="${e.target.result}" alt="Preview">
                            <div class="image-info">
                                <p>New image: ${file.name}</p>
                                <small>${(file.size / 1024).toFixed(1)} KB</small>
                            </div>
                            <button type="button" class="remove-image" id="removeImageBtn">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    imageUploadArea.insertAdjacentHTML('afterend', previewHtml);
                    
                    document.getElementById('removeImageBtn')?.addEventListener('click', removeImage);
                }
            };
            reader.readAsDataURL(file);
        }
        
        function removeImage() {
            const currentImage = document.getElementById('currentImage');
            if (currentImage) currentImage.remove();
            if (imageInput) imageInput.value = '';
            
            // Add hidden input to indicate image removal for edit mode
            let removeField = document.querySelector('input[name="remove_image"]');
            if (!removeField) {
                removeField = document.createElement('input');
                removeField.type = 'hidden';
                removeField.name = 'remove_image';
                removeField.value = '1';
                document.getElementById('postForm').appendChild(removeField);
            }
        }
        
        const removeImageBtn = document.getElementById('removeImageBtn');
        if (removeImageBtn) {
            removeImageBtn.addEventListener('click', removeImage);
        }
        
        // Form submission loading state
        const submitBtn = document.getElementById('submitBtn');
        const postForm = document.getElementById('postForm');
        
        if (postForm) {
            postForm.addEventListener('submit', function(e) {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="loading"></span> Saving...';
                }
            });
        }
        
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
        
        // Close sidebar on link click (mobile)
        document.querySelectorAll('.sidebar-menu-item').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) {
                    setTimeout(closeSidebar, 150);
                }
            });
        });
        
        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                if (window.innerWidth > 1024 && adminSidebar.classList.contains('sidebar-open')) {
                    closeSidebar();
                }
            }, 250);
        });
        
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