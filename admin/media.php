<?php
/**
 * Media Library - Admin Panel
 * Manage all uploaded images, audio, and other media files from all directories
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

// Initialize variables
$success_message = '';
$error_message = '';
$all_media = [];

// Define all upload directories to scan
$upload_directories = [
    '../assets/uploads/',
    '../assets/uploads/about/',
    '../assets/uploads/blog/',
    '../assets/uploads/cta/',
    '../assets/uploads/hero/',
    '../assets/uploads/images/',
    '../uploads/',
    '../uploads/about/',
    '../uploads/audio/',
    '../uploads/images/',
    '../uploads/posts/',
];

// Function to scan directory and get all media files
function scanDirectoryForMedia($directory, $relative_path = '') {
    $media_files = [];
    
    if (!is_dir($directory)) {
        return $media_files;
    }
    
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'wav', 'ogg', 'mp4'];
    
    $files = scandir($directory);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'index.html') {
            continue;
        }
        
        $file_path = $directory . $file;
        $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        if (is_file($file_path) && in_array($file_extension, $allowed_extensions)) {
            $file_size = filesize($file_path);
            $file_modified = filemtime($file_path);
            $file_type = getFileMimeType($file_path);
            $is_image = in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            
            // Determine relative path for web access
            $web_path = str_replace('../', '', $file_path);
            
            $media_files[] = [
                'id' => null, // No database ID for scanned files
                'file_name' => $file,
                'file_path' => $web_path,
                'full_path' => $file_path,
                'file_type' => $file_type,
                'file_size' => $file_size,
                'file_extension' => $file_extension,
                'is_image' => $is_image,
                'created_at' => date('Y-m-d H:i:s', $file_modified),
                'directory' => $relative_path ?: basename(dirname($directory)),
                'in_database' => false
            ];
        } elseif (is_dir($file_path) && $file !== '.' && $file !== '..') {
            // Recursively scan subdirectories (optional - enable if needed)
            // $sub_media = scanDirectoryForMedia($file_path . '/', $relative_path . $file . '/');
            // $media_files = array_merge($media_files, $sub_media);
        }
    }
    
    return $media_files;
}

// Function to get file mime type
function getFileMimeType($file_path) {
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    $mime_types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'mp4' => 'video/mp4',
    ];
    
    return $mime_types[$extension] ?? 'application/octet-stream';
}

// Get media from database
function getMediaFromDatabase() {
    global $conn;
    
    $media = [];
    $result = $conn->query("SELECT * FROM media ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['in_database'] = true;
            $row['is_image'] = strpos($row['file_type'], 'image') !== false;
            $media[] = $row;
        }
    }
    return $media;
}

// Sync database media with actual files
function syncMediaWithDatabase() {
    global $conn;
    
    // Get all database records
    $db_media = [];
    $result = $conn->query("SELECT id, file_path FROM media");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $db_media[$row['file_path']] = $row['id'];
        }
    }
    
    // Get all files from directories
    $upload_directories = [
        '../uploads/images/',
        '../uploads/about/',
        '../uploads/audio/',
        '../uploads/posts/',
        '../assets/uploads/',
        '../assets/uploads/about/',
        '../assets/uploads/blog/',
        '../assets/uploads/cta/',
        '../assets/uploads/hero/',
        '../assets/uploads/images/',
    ];
    
    $found_files = [];
    foreach ($upload_directories as $dir) {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..' || $file === 'index.html') continue;
                $file_path = str_replace('../', '', $dir) . $file;
                if (is_file($dir . $file)) {
                    $found_files[] = $file_path;
                }
            }
        }
    }
    
    // Remove records for files that don't exist
    foreach ($db_media as $file_path => $id) {
        if (!in_array($file_path, $found_files)) {
            // File doesn't exist, delete from database
            $stmt = $conn->prepare("DELETE FROM media WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
    }
    
    return count($found_files);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_media'])) {
    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['media_file'];
        $upload_to = $_POST['upload_to'] ?? 'uploads/images/';
        
        $upload_result = uploadMediaFileToDirectory($file, $upload_to);
        if ($upload_result['success']) {
            $success_message = 'File uploaded successfully to ' . $upload_to . '!';
            logAdminAction('upload_media', 'Uploaded file: ' . $upload_result['filename']);
        } else {
            $error_message = $upload_result['error'];
        }
    } else {
        $error_message = 'Please select a file to upload.';
    }
}

// Handle delete media
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $file_path = $_GET['delete'];
    $confirm = isset($_GET['confirm']) ? $_GET['confirm'] : '';
    
    if ($confirm === 'yes') {
        $full_path = '../' . $file_path;
        if (file_exists($full_path)) {
            if (unlink($full_path)) {
                // Also remove from database if exists
                $stmt = $conn->prepare("DELETE FROM media WHERE file_path = ?");
                $stmt->bind_param("s", $file_path);
                $stmt->execute();
                
                $success_message = 'File deleted successfully!';
                logAdminAction('delete_media', 'Deleted file: ' . $file_path);
            } else {
                $error_message = 'Failed to delete file.';
            }
        } else {
            $error_message = 'File not found.';
        }
    } else {
        echo "<script>if(confirm('Are you sure you want to delete this file? This action cannot be undone.')){window.location.href='?delete=" . urlencode($file_path) . "&confirm=yes';}</script>";
    }
}

// Handle bulk delete
if (isset($_POST['bulk_action']) && isset($_POST['media_paths']) && is_array($_POST['media_paths'])) {
    $media_paths = $_POST['media_paths'];
    $bulk_action = $_POST['bulk_action'];
    
    if ($bulk_action === 'delete') {
        $deleted_count = 0;
        foreach ($media_paths as $file_path) {
            $full_path = '../' . $file_path;
            if (file_exists($full_path)) {
                if (unlink($full_path)) {
                    // Remove from database if exists
                    $stmt = $conn->prepare("DELETE FROM media WHERE file_path = ?");
                    $stmt->bind_param("s", $file_path);
                    $stmt->execute();
                    $deleted_count++;
                }
            }
        }
        if ($deleted_count > 0) {
            $success_message = $deleted_count . ' file(s) deleted successfully!';
            logAdminAction('bulk_delete', 'Deleted ' . $deleted_count . ' files');
        } else {
            $error_message = 'Failed to delete selected files.';
        }
    }
}

// Function to upload file to specific directory
function uploadMediaFileToDirectory($file, $target_dir_relative) {
    $target_dir = "../" . $target_dir_relative;
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $imageFileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp3', 'wav', 'ogg'];
    
    if (!in_array($imageFileType, $allowed_types)) {
        return ['success' => false, 'error' => 'File type not allowed. Allowed: JPG, PNG, GIF, WEBP, MP3, WAV, OGG'];
    }
    
    // Check file size (max 10MB)
    if ($file['size'] > 10000000) {
        return ['success' => false, 'error' => 'Sorry, your file is too large. Max 10MB.'];
    }
    
    // Generate unique filename to avoid conflicts
    $unique_id = uniqid() . '_' . time();
    $new_filename = $unique_id . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Save to database
        global $conn, $admin_id;
        $file_path = $target_dir_relative . $new_filename;
        $file_type = $file['type'];
        $original_name = $file['name'];
        
        $stmt = $conn->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssi", $original_name, $file_path, $file_type, $file['size'], $admin_id);
        $stmt->execute();
        
        return ['success' => true, 'filename' => $original_name, 'filepath' => $file_path];
    } else {
        return ['success' => false, 'error' => 'Sorry, there was an error uploading your file.'];
    }
}

// Get filter parameters
$media_type = isset($_GET['type']) ? $_GET['type'] : '';
$directory_filter = isset($_GET['directory']) ? $_GET['directory'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 24;
$offset = ($page - 1) * $per_page;

// Scan all directories for media files
$all_media = [];
foreach ($upload_directories as $dir) {
    if (is_dir($dir)) {
        $media_from_dir = scanDirectoryForMedia($dir, '');
        $all_media = array_merge($all_media, $media_from_dir);
    }
}

// Also get media from database (for files that might be in non-scanned locations)
$db_media = getMediaFromDatabase();
$all_media = array_merge($all_media, $db_media);

// Remove duplicates based on file_path
$unique_media = [];
foreach ($all_media as $item) {
    $key = $item['file_path'];
    if (!isset($unique_media[$key]) || ($item['in_database'] && !$unique_media[$key]['in_database'])) {
        $unique_media[$key] = $item;
    }
}
$all_media = array_values($unique_media);

// Sort by date (newest first)
usort($all_media, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Apply filters
$filtered_media = $all_media;
if (!empty($media_type)) {
    $filtered_media = array_filter($filtered_media, function($item) use ($media_type) {
        if ($media_type === 'image') {
            return $item['is_image'];
        } elseif ($media_type === 'audio') {
            return strpos($item['file_type'], 'audio') !== false;
        }
        return true;
    });
}

if (!empty($directory_filter)) {
    $filtered_media = array_filter($filtered_media, function($item) use ($directory_filter) {
        return strpos($item['file_path'], $directory_filter) !== false;
    });
}

if (!empty($search)) {
    $filtered_media = array_filter($filtered_media, function($item) use ($search) {
        return stripos($item['file_name'], $search) !== false;
    });
}

// Get unique directories for filter
$directories = [];
foreach ($all_media as $item) {
    $dir = dirname($item['file_path']);
    if (!in_array($dir, $directories)) {
        $directories[] = $dir;
    }
}
sort($directories);

// Get total count and paginate
$total_media = count($filtered_media);
$total_pages = ceil($total_media / $per_page);
$paginated_media = array_slice($filtered_media, $offset, $per_page);

// Calculate statistics
$total_files = count($all_media);
$total_images = count(array_filter($all_media, function($item) { return $item['is_image']; }));
$total_audio = count(array_filter($all_media, function($item) { return strpos($item['file_type'], 'audio') !== false; }));
$total_size = array_sum(array_column($all_media, 'file_size'));
$total_size_formatted = formatFileSize($total_size);

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
    <title>Media Library - Admin Panel</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <!-- Lightbox for image preview -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css">
    
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-card i {
            font-size: 32px;
            color: <?php echo $primary_color; ?>;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-card .label {
            font-size: 13px;
            color: #666;
        }
        
        /* Upload Section */
        .upload-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .upload-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .upload-form .form-group {
            flex: 1;
            min-width: 200px;
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
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 20px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 30px;
            text-decoration: none;
            color: #666;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: <?php echo $primary_color; ?>;
            border-color: <?php echo $primary_color; ?>;
            color: white;
        }
        
        .directory-filter {
            min-width: 200px;
        }
        
        .directory-filter select {
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 13px;
            background: white;
        }
        
        .search-box {
            flex: 1;
            min-width: 200px;
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
        }
        
        /* Media Grid */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .media-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            position: relative;
        }
        
        .media-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .media-preview {
            height: 180px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .media-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .media-preview audio {
            width: 100%;
            padding: 20px;
        }
        
        .file-icon {
            font-size: 60px;
            color: #adb5bd;
        }
        
        .directory-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 10px;
            z-index: 5;
        }
        
        .media-info {
            padding: 15px;
        }
        
        .media-name {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            word-break: break-all;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .media-meta {
            font-size: 11px;
            color: #999;
            margin-bottom: 10px;
        }
        
        .media-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
        }
        
        .action-btn.view {
            background: #17a2b8;
            color: white;
        }
        
        .action-btn.delete {
            background: #dc3545;
            color: white;
        }
        
        .checkbox-col {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 10;
        }
        
        .checkbox-col input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        /* Bulk Actions */
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
            padding: 0 10px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .page-link:hover,
        .page-item.active .page-link {
            background: <?php echo $primary_color; ?>;
            border-color: <?php echo $primary_color; ?>;
            color: white;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
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
        
        .btn-primary {
            background: <?php echo $primary_color; ?>;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            cursor: pointer;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .copy-success {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            z-index: 9999;
            animation: fadeOut 2s ease;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
        
        @media (max-width: 768px) {
            .media-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 15px;
            }
            
            .media-preview {
                height: 150px;
            }
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
                    <h1>Media Library</h1>
                    <p>Manage all your images, audio files, and documents from all upload directories</p>
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
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <i class="fas fa-folder-open"></i>
                    <div class="number"><?php echo $total_files; ?></div>
                    <div class="label">Total Files</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-image"></i>
                    <div class="number"><?php echo $total_images; ?></div>
                    <div class="label">Images</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-music"></i>
                    <div class="number"><?php echo $total_audio; ?></div>
                    <div class="label">Audio Files</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-database"></i>
                    <div class="number"><?php echo $total_size_formatted; ?></div>
                    <div class="label">Total Size</div>
                </div>
            </div>
            
            <!-- Upload Section -->
            <div class="upload-section">
                <h3 style="margin-bottom: 15px;"><i class="fas fa-cloud-upload-alt"></i> Upload New File</h3>
                <form method="POST" action="" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="upload_media" value="1">
                    <div class="form-group" style="flex: 2;">
                        <div class="image-upload-area" id="uploadArea">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload or drag and drop</p>
                            <small>Images: JPG, PNG, GIF, WEBP (max 10MB)<br>Audio: MP3, WAV, OGG (max 10MB)</small>
                            <input type="file" id="media_file" name="media_file" accept="image/*,audio/*" style="display: none;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Upload to Directory</label>
                        <select name="upload_to" class="form-control">
                            <option value="uploads/images/">uploads/images/</option>
                            <option value="uploads/about/">uploads/about/</option>
                            <option value="uploads/audio/">uploads/audio/</option>
                            <option value="assets/uploads/">assets/uploads/</option>
                            <option value="assets/uploads/hero/">assets/uploads/hero/</option>
                            <option value="assets/uploads/blog/">assets/uploads/blog/</option>
                            <option value="assets/uploads/about/">assets/uploads/about/</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn-primary">Upload File</button>
                    </div>
                </form>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-group">
                    <a href="media.php" class="filter-btn <?php echo empty($media_type) && empty($directory_filter) ? 'active' : ''; ?>">All</a>
                    <a href="media.php?type=image" class="filter-btn <?php echo $media_type == 'image' ? 'active' : ''; ?>">Images</a>
                    <a href="media.php?type=audio" class="filter-btn <?php echo $media_type == 'audio' ? 'active' : ''; ?>">Audio</a>
                </div>
                
                <div class="directory-filter">
                    <select id="directoryFilter" onchange="window.location.href='media.php?directory=' + encodeURIComponent(this.value) + '&type=<?php echo $media_type; ?>&search=<?php echo urlencode($search); ?>'">
                        <option value="">All Directories</option>
                        <?php foreach ($directories as $dir): ?>
                        <option value="<?php echo htmlspecialchars($dir); ?>" <?php echo $directory_filter == $dir ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dir); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search files..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            
            <!-- Bulk Actions -->
            <form method="POST" action="" id="bulkForm">
                <div class="bulk-actions" id="bulkActions">
                    <div class="bulk-select-all">
                        <input type="checkbox" id="selectAll">
                        <label for="selectAll">Select All</label>
                    </div>
                    <select name="bulk_action" class="form-control" style="width: auto;">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete Selected</option>
                    </select>
                    <button type="submit" class="btn-primary" onclick="return confirmBulkAction()">Apply</button>
                    <span id="selectedCount" style="font-size: 12px; color: #666;"></span>
                </div>
                
                <!-- Media Grid -->
                <div class="media-grid">
                    <?php if (!empty($paginated_media)): ?>
                        <?php foreach ($paginated_media as $media): 
                            $is_image = $media['is_image'];
                        ?>
                        <div class="media-item">
                            <div class="checkbox-col">
                                <input type="checkbox" name="media_paths[]" value="<?php echo htmlspecialchars($media['file_path']); ?>" class="media-checkbox">
                            </div>
                            <div class="media-preview">
                                <?php if ($is_image): ?>
                                    <a href="../<?php echo $media['file_path']; ?>" data-lightbox="media-gallery" data-title="<?php echo htmlspecialchars($media['file_name']); ?>">
                                        <img src="../<?php echo $media['file_path']; ?>" alt="<?php echo htmlspecialchars($media['file_name']); ?>">
                                    </a>
                                <?php elseif (strpos($media['file_type'], 'audio') !== false): ?>
                                    <i class="fas fa-music file-icon"></i>
                                    <audio controls style="width: 100%; padding: 10px;">
                                        <source src="../<?php echo $media['file_path']; ?>" type="<?php echo $media['file_type']; ?>">
                                    </audio>
                                <?php else: ?>
                                    <i class="fas fa-file file-icon"></i>
                                <?php endif; ?>
                                <div class="directory-badge">
                                    <?php echo htmlspecialchars(dirname($media['file_path'])); ?>
                                </div>
                            </div>
                            <div class="media-info">
                                <div class="media-name" title="<?php echo htmlspecialchars($media['file_name']); ?>">
                                    <?php echo htmlspecialchars(substr($media['file_name'], 0, 25)) . (strlen($media['file_name']) > 25 ? '...' : ''); ?>
                                </div>
                                <div class="media-meta">
                                    <?php echo formatFileSize($media['file_size']); ?> • <?php echo date('M d, Y', strtotime($media['created_at'])); ?>
                                </div>
                                <div class="media-actions">
                                    <button class="action-btn view" onclick="copyToClipboard('<?php echo htmlspecialchars($media['file_path']); ?>')">
                                        <i class="fas fa-copy"></i> Copy Path
                                    </button>
                                    <a href="?delete=<?php echo urlencode($media['file_path']); ?>" class="action-btn delete" onclick="return false;" data-path="<?php echo htmlspecialchars($media['file_path']); ?>">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px; grid-column: 1 / -1;">
                            <i class="fas fa-folder-open" style="font-size: 60px; color: #ccc;"></i>
                            <h3 style="margin-top: 15px; color: #666;">No Media Files Found</h3>
                            <p style="color: #999;">Upload your first image or audio file to get started.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&type=<?php echo $media_type; ?>&directory=<?php echo urlencode($directory_filter); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&type=<?php echo $media_type; ?>&directory=<?php echo urlencode($directory_filter); ?>&search=<?php echo urlencode($search); ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $media_type; ?>&directory=<?php echo urlencode($directory_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&type=<?php echo $media_type; ?>&directory=<?php echo urlencode($directory_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $total_pages; ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&type=<?php echo $media_type; ?>&directory=<?php echo urlencode($directory_filter); ?>&search=<?php echo urlencode($search); ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>
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
        
        // Image upload area
        const uploadArea = document.getElementById('uploadArea');
        const mediaFile = document.getElementById('media_file');
        
        if (uploadArea) {
            uploadArea.addEventListener('click', () => mediaFile.click());
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '<?php echo $primary_color; ?>';
            });
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.style.borderColor = '#e9ecef';
            });
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.style.borderColor = '#e9ecef';
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    mediaFile.files = files;
                    mediaFile.form.submit();
                }
            });
        }
        
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    const search = this.value;
                    window.location.href = 'media.php?search=' + encodeURIComponent(search) + '&type=<?php echo $media_type; ?>&directory=<?php echo urlencode($directory_filter); ?>';
                }
            });
        }
        
        // Copy to clipboard function
        function copyToClipboard(path) {
            const fullPath = '<?php echo SITE_URL; ?>/' + path;
            navigator.clipboard.writeText(fullPath).then(function() {
                const toast = document.createElement('div');
                toast.className = 'copy-success';
                toast.innerHTML = '<i class="fas fa-check"></i> Path copied: ' + fullPath;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            });
        }
        
        // Bulk actions
        const selectAllCheckbox = document.getElementById('selectAll');
        const mediaCheckboxes = document.querySelectorAll('.media-checkbox');
        const selectedCountSpan = document.getElementById('selectedCount');
        
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.media-checkbox:checked');
            const count = checked.length;
            if (selectedCountSpan) {
                if (count > 0) {
                    selectedCountSpan.textContent = count + ' selected';
                } else {
                    selectedCountSpan.textContent = '';
                }
            }
        }
        
        function updateSelectAll() {
            if (selectAllCheckbox) {
                const allChecked = mediaCheckboxes.length > 0 && 
                    Array.from(mediaCheckboxes).every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }
        }
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                mediaCheckboxes.forEach(cb => cb.checked = this.checked);
                updateSelectedCount();
            });
        }
        
        mediaCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                updateSelectedCount();
                updateSelectAll();
            });
        });
        
        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const checked = document.querySelectorAll('.media-checkbox:checked');
            
            if (checked.length === 0) {
                alert('Please select at least one file.');
                return false;
            }
            
            if (action === 'delete') {
                return confirm('Are you sure you want to delete ' + checked.length + ' file(s)? This action cannot be undone.');
            } else if (action === '') {
                alert('Please select a bulk action.');
                return false;
            }
            
            return true;
        }
        
        // Delete confirmation
        document.querySelectorAll('.action-btn.delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const path = this.getAttribute('data-path');
                if (confirm('Are you sure you want to delete this file? This action cannot be undone.')) {
                    window.location.href = '?delete=' + encodeURIComponent(path);
                }
            });
        });
        
        updateSelectedCount();
        
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
    </script>
</body>
</html>