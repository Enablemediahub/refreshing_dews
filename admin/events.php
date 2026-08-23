<?php
/**
 * Admin Events Management
 * Complete events management with image upload functionality
 * Images saved to: uploads/events/ (featured) and uploads/events/gallery/ (gallery)
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

// Define upload directories (relative to project root)
$project_root = dirname(__DIR__); // Project root: painlesslyf
$upload_base_dir = $project_root . '/uploads/events/';
$gallery_base_dir = $upload_base_dir . 'gallery/';

// Create directories if they don't exist
if (!file_exists($upload_base_dir)) {
    mkdir($upload_base_dir, 0777, true);
}
if (!file_exists($gallery_base_dir)) {
    mkdir($gallery_base_dir, 0777, true);
}

// Verify directories are writable
if (!is_writable($upload_base_dir)) {
    chmod($upload_base_dir, 0777);
}
if (!is_writable($gallery_base_dir)) {
    chmod($gallery_base_dir, 0777);
}

// Helper function to upload image
function uploadEventImage($file, $target_dir, $is_gallery = false) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Check if file is valid
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Invalid file upload.'];
    }
    
    // Check file type
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, GIF, and WEBP images are allowed.'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size must be less than 5MB.'];
    }
    
    // Create filename and path
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $target_path = $target_dir . $filename;
    
    // Determine the database path (relative to project root)
    if ($is_gallery) {
        $db_path = 'uploads/events/gallery/' . $filename;
    } else {
        $db_path = 'uploads/events/' . $filename;
    }
    
    // Upload the file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'path' => $db_path];
    }
    
    return ['success' => false, 'error' => 'Failed to upload file. Please check directory permissions.'];
}

// Handle form submissions
$message = '';
$error = '';
$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Get event images to delete files
    $stmt = $conn->prepare("SELECT featured_image, gallery_images FROM events WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if ($event) {
        // Delete featured image (path is relative to project root)
        if (!empty($event['featured_image'])) {
            $full_path = dirname(__DIR__) . '/' . $event['featured_image'];
            if (file_exists($full_path)) {
                unlink($full_path);
            }
        }
        
        // Delete gallery images
        $gallery_images = json_decode($event['gallery_images'], true);
        if (is_array($gallery_images)) {
            foreach ($gallery_images as $image) {
                $full_path = dirname(__DIR__) . '/' . $image;
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }
        }
        
        // Delete from database
        $delete_stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        if ($delete_stmt->execute()) {
            $message = "Event deleted successfully!";
            if (function_exists('logAdminAction')) {
                logAdminAction($admin_id, 'delete_event', "Deleted event ID: $delete_id");
            }
        } else {
            $error = "Failed to delete event.";
        }
    }
    header("Location: events.php?msg=" . urlencode($message) . "&error=" . urlencode($error));
    exit();
}

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $slug = createSlug($title);
    $description = trim($_POST['description'] ?? '');
    $content = $_POST['content'] ?? '';
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = $_POST['status'] ?? 'upcoming';
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Validate required fields
    if (empty($title) || empty($event_date)) {
        $error = "Please fill in all required fields.";
    } else {
        // Handle featured image upload
        $featured_image = '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadEventImage($_FILES['featured_image'], $upload_base_dir, false);
            if ($upload_result['success']) {
                $featured_image = $upload_result['path'];
            } else {
                $error = $upload_result['error'];
            }
        }
        
        // Handle gallery images upload
        $gallery_images = [];
        $gallery_captions = [];
        
        if (isset($_FILES['gallery_images']) && !empty($_FILES['gallery_images']['name'][0])) {
            $files = $_FILES['gallery_images'];
            $captions = $_POST['gallery_captions'] ?? [];
            
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                    $upload_result = uploadEventImage($file, $gallery_base_dir, true);
                    if ($upload_result['success']) {
                        $gallery_images[] = $upload_result['path'];
                        $gallery_captions[] = $captions[$i] ?? '';
                    }
                }
            }
        }
        
        // Handle existing gallery images (if editing)
        if ($event_id > 0 && isset($_POST['existing_gallery_images']) && !empty($_POST['existing_gallery_images'])) {
            // existing_gallery_images is sent as a JSON string, decode it
            $existing_images = [];
            if (is_string($_POST['existing_gallery_images'])) {
                $existing_images = json_decode($_POST['existing_gallery_images'], true) ?: [];
            } elseif (is_array($_POST['existing_gallery_images'])) {
                $existing_images = $_POST['existing_gallery_images'];
            }
            
            $existing_captions = [];
            if (isset($_POST['existing_gallery_captions'])) {
                if (is_string($_POST['existing_gallery_captions'])) {
                    $existing_captions = json_decode($_POST['existing_gallery_captions'], true) ?: [];
                } elseif (is_array($_POST['existing_gallery_captions'])) {
                    $existing_captions = $_POST['existing_gallery_captions'];
                }
            }
            
            foreach ($existing_images as $index => $img) {
                // Check if this image should be deleted
                $delete_key = 'delete_gallery_' . $index;
                if (!isset($_POST[$delete_key]) || $_POST[$delete_key] != '1') {
                    $gallery_images[] = $img;
                    $gallery_captions[] = $existing_captions[$index] ?? '';
                } else {
                    // Delete file if marked for deletion
                    $full_path = dirname(__DIR__) . '/' . $img;
                    if (file_exists($full_path)) {
                        unlink($full_path);
                    }
                }
            }
        }
        
        $gallery_images_json = json_encode($gallery_images);
        $gallery_captions_json = json_encode($gallery_captions);
        
        if ($event_id > 0) {
            // Update existing event
            $sql = "UPDATE events SET 
                    title = ?, slug = ?, description = ?, content = ?, 
                    event_date = ?, event_time = ?, location = ?, address = ?, 
                    status = ?, featured = ?";
            $params = [$title, $slug, $description, $content, $event_date, $event_time, $location, $address, $status, $featured];
            $types = "sssssssssi";
            
            // Only update featured image if a new one was uploaded
            if (!empty($featured_image)) {
                // Get old featured image to delete
                $old_stmt = $conn->prepare("SELECT featured_image FROM events WHERE id = ?");
                $old_stmt->bind_param("i", $event_id);
                $old_stmt->execute();
                $old_result = $old_stmt->get_result();
                $old_event = $old_result->fetch_assoc();
                if (!empty($old_event['featured_image'])) {
                    $full_path = dirname(__DIR__) . '/' . $old_event['featured_image'];
                    if (file_exists($full_path)) {
                        unlink($full_path);
                    }
                }
                
                $sql .= ", featured_image = ?";
                $params[] = $featured_image;
                $types .= "s";
            }
            
            $sql .= ", gallery_images = ?, gallery_captions = ? WHERE id = ?";
            $params[] = $gallery_images_json;
            $params[] = $gallery_captions_json;
            $params[] = $event_id;
            $types .= "ssi";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            
            if ($stmt->execute()) {
                $message = "Event updated successfully!";
                if (function_exists('logAdminAction')) {
                    logAdminAction($admin_id, 'update_event', "Updated event: $title (ID: $event_id)");
                }
                header("Location: events.php?msg=" . urlencode($message));
                exit();
            } else {
                $error = "Failed to update event: " . $conn->error;
            }
        } else {
            // Create new event
            $sql = "INSERT INTO events (title, slug, description, content, event_date, event_time, 
                    location, address, featured_image, gallery_images, gallery_captions, status, featured, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssssi", $title, $slug, $description, $content, $event_date, 
                              $event_time, $location, $address, $featured_image, $gallery_images_json, 
                              $gallery_captions_json, $status, $featured);
            
            if ($stmt->execute()) {
                $message = "Event created successfully!";
                if (function_exists('logAdminAction')) {
                    logAdminAction($admin_id, 'add_event', "Added new event: $title");
                }
                header("Location: events.php?msg=" . urlencode($message));
                exit();
            } else {
                $error = "Failed to create event: " . $conn->error;
            }
        }
    }
}

// Get event for editing
$event = null;
if ($event_id > 0 && ($action == 'edit' || $action == 'view')) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    
    if (!$event) {
        header("Location: events.php");
        exit();
    }
    
    // Decode gallery data - ensure we decode properly
    $event['gallery_images'] = [];
    $event['gallery_captions'] = [];
    
    if (!empty($event['gallery_images'])) {
        $decoded = json_decode($event['gallery_images'], true);
        if (is_array($decoded)) {
            $event['gallery_images'] = $decoded;
        }
    }
    
    if (!empty($event['gallery_captions'])) {
        $decoded = json_decode($event['gallery_captions'], true);
        if (is_array($decoded)) {
            $event['gallery_captions'] = $decoded;
        }
    }
}

// Get events list
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$query = "SELECT * FROM events WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM events WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $count_query .= " AND (title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $count_query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get total count
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_events = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_events / $per_page);

// Get events for current page
$query .= " ORDER BY event_date DESC, created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$per_page, $offset]));
} else {
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$events_result = $stmt->get_result();

// Get message/error from URL
if (isset($_GET['msg'])) {
    $message = urldecode($_GET['msg']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

// Helper function to get setting (if not already defined)
if (!function_exists('getSetting')) {
    function getSetting($key, $default = '') {
        global $conn;
        static $settings = null;
        
        if ($settings === null) {
            $settings = [];
            if (isset($conn) && !$conn->connect_error) {
                $result = $conn->query("SELECT setting_key, setting_value FROM settings");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
        }
        
        return $settings[$key] ?? $default;
    }
}

// Helper function to get image URL for display
function getImageDisplayPath($path) {
    if (empty($path)) {
        return '#';
    }
    return '../' . $path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Management - Admin Panel</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <!-- CKEditor -->
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
    
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
        
        /* Sidebar Styles */
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
            max-width: 150px;
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
            font-size: 14px;
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
            border-left-color: #4a7c59;
        }
        
        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 15px 20px;
        }
        
        .sidebar-menu-label {
            padding: 10px 25px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
        }
        
        /* Main Content */
        .admin-main {
            flex: 1;
            padding: 30px;
            overflow-x: auto;
        }
        
        /* Top Navigation */
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
            background: linear-gradient(135deg, #4a7c59, #2c4a3b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
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
        
        /* Action Bar */
        .action-bar {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #4a7c59;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2c4a3b;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            min-width: 250px;
        }
        
        .search-box select {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .event-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-upcoming {
            background: #d4edda;
            color: #155724;
        }
        
        .status-completed {
            background: #cfe2ff;
            color: #004085;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .action-btn-view {
            background: #17a2b8;
            color: white;
        }
        
        .action-btn-edit {
            background: #4a7c59;
            color: white;
        }
        
        .action-btn-delete {
            background: #dc3545;
            color: white;
        }
        
        /* Pagination */
        .pagination {
            padding: 20px;
            display: flex;
            justify-content: center;
            gap: 5px;
        }
        
        .page-link {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #4a7c59;
        }
        
        .page-link.active {
            background: #4a7c59;
            color: white;
            border-color: #4a7c59;
        }
        
        .page-link:hover:not(.active) {
            background: #f0f0f0;
        }
        
        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input {
            width: auto;
        }
        
        /* Image Upload */
        .image-preview {
            margin-top: 10px;
            max-width: 300px;
        }
        
        .image-preview img {
            width: 100%;
            border-radius: 8px;
        }
        
        .gallery-item {
            display: inline-block;
            margin: 10px;
            position: relative;
            width: 150px;
        }
        
        .gallery-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .gallery-item .delete-checkbox {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0,0,0,0.7);
            padding: 5px;
            border-radius: 5px;
        }
        
        .gallery-item .caption-input {
            margin-top: 5px;
            width: 100%;
            padding: 5px;
            font-size: 12px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                display: none;
            }
            
            .admin-main {
                padding: 15px;
            }
            
            .action-bar {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
                flex-direction: column;
            }
            
            .search-box input,
            .search-box select {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php $current_page = basename(__FILE__); include __DIR__ . '/includes/sidebar.php'; ?>
        <!-- Main Content -->
        <div class="admin-main">
            <!-- Top Navigation -->
            <div class="top-nav">
                <div class="top-nav-title">
                    <h1>Events Management</h1>
                    <p>Manage your events, gatherings, and gallery images</p>
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
            
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($action == 'edit' || $action == 'add'): ?>
            <!-- Add/Edit Event Form -->
            <div class="form-container">
                <h2 style="margin-bottom: 20px;">
                    <?php echo $action == 'edit' ? 'Edit Event' : 'Add New Event'; ?>
                </h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Event Title *</label>
                            <input type="text" id="title" name="title" required 
                                   value="<?php echo htmlspecialchars($event['title'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="event_date">Event Date *</label>
                            <input type="date" id="event_date" name="event_date" required 
                                   value="<?php echo htmlspecialchars($event['event_date'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="event_time">Event Time</label>
                            <input type="time" id="event_time" name="event_time" 
                                   value="<?php echo htmlspecialchars($event['event_time'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location" 
                                   value="<?php echo htmlspecialchars($event['location'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2"><?php echo htmlspecialchars($event['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="upcoming" <?php echo (($event['status'] ?? '') == 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                <option value="completed" <?php echo (($event['status'] ?? '') == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo (($event['status'] ?? '') == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label>
                                <input type="checkbox" name="featured" value="1" <?php echo (($event['featured'] ?? 0) == 1) ? 'checked' : ''; ?>>
                                Featured Event
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Short Description</label>
                        <textarea id="description" name="description" rows="3" placeholder="Brief description for card view..."><?php echo htmlspecialchars($event['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="content">Full Content</label>
                        <textarea id="content" name="content" rows="10"><?php echo htmlspecialchars($event['content'] ?? ''); ?></textarea>
                        <script>
                            ClassicEditor
                                .create(document.querySelector('#content'))
                                .catch(error => {
                                    console.error(error);
                                });
                        </script>
                    </div>
                    
                    <!-- Featured Image Upload -->
                    <div class="form-group">
                        <label for="featured_image">Featured Image</label>
                        <input type="file" id="featured_image" name="featured_image" accept="image/*">
                        <?php if (!empty($event['featured_image'])): ?>
                        <div class="image-preview">
                            <img src="../<?php echo $event['featured_image']; ?>" alt="Current featured image" style="max-width: 200px;">
                            <p style="font-size: 12px; color: #666; margin-top: 5px;">Current image (stored in: uploads/events/)</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Gallery Images Upload -->
                    <div class="form-group">
                        <label>Gallery Images</label>
                        <input type="file" id="gallery_images" name="gallery_images[]" accept="image/*" multiple>
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">You can select multiple images. Hold Ctrl/Cmd to select multiple. Images will be stored in: uploads/events/gallery/</p>
                        
                        <?php if (!empty($event['gallery_images']) && is_array($event['gallery_images'])): ?>
                        <div style="margin-top: 15px;">
                            <h4>Existing Gallery Images</h4>
                            <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                                <?php foreach ($event['gallery_images'] as $index => $image): ?>
                                <div class="gallery-item">
                                    <img src="../<?php echo $image; ?>" alt="Gallery image">
                                    <div class="delete-checkbox">
                                        <label>
                                            <input type="checkbox" name="delete_gallery_<?php echo $index; ?>" value="1">
                                            Delete
                                        </label>
                                    </div>
                                    <input type="text" class="caption-input" name="gallery_captions[<?php echo $index; ?>]" 
                                           placeholder="Caption" value="<?php echo htmlspecialchars($event['gallery_captions'][$index] ?? ''); ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <input type="hidden" name="existing_gallery_images" value='<?php echo htmlspecialchars(json_encode($event['gallery_images'])); ?>'>
                            <input type="hidden" name="existing_gallery_captions" value='<?php echo htmlspecialchars(json_encode($event['gallery_captions'])); ?>'>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> 
                            <?php echo $action == 'edit' ? 'Update Event' : 'Create Event'; ?>
                        </button>
                        <a href="events.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
            
            <?php elseif ($action == 'view' && $event): ?>
            <!-- View Event Details -->
            <div class="form-container">
                <h2 style="margin-bottom: 20px;">Event Details: <?php echo htmlspecialchars($event['title']); ?></h2>
                
                <div style="margin-bottom: 20px;">
                    <a href="events.php?action=edit&id=<?php echo $event['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Event
                    </a>
                    <a href="events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <strong>Event Title:</strong> <?php echo htmlspecialchars($event['title']); ?>
                    </div>
                    <div class="form-group">
                        <strong>Event Date:</strong> <?php echo date('F j, Y', strtotime($event['event_date'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($event['event_time'])): ?>
                <div class="form-group">
                    <strong>Event Time:</strong> <?php echo htmlspecialchars($event['event_time']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($event['location'])): ?>
                <div class="form-group">
                    <strong>Location:</strong> <?php echo htmlspecialchars($event['location']); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($event['address'])): ?>
                <div class="form-group">
                    <strong>Address:</strong> <?php echo nl2br(htmlspecialchars($event['address'])); ?>
                </div>
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <strong>Status:</strong> 
                        <span class="status-badge status-<?php echo $event['status']; ?>">
                            <?php echo ucfirst($event['status']); ?>
                        </span>
                    </div>
                    <div class="form-group">
                        <strong>Featured:</strong> <?php echo $event['featured'] ? 'Yes' : 'No'; ?>
                    </div>
                </div>
                
                <?php if (!empty($event['description'])): ?>
                <div class="form-group">
                    <strong>Description:</strong>
                    <p><?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($event['content'])): ?>
                <div class="form-group">
                    <strong>Full Content:</strong>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 5px;">
                        <?php echo $event['content']; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($event['featured_image'])): ?>
                <div class="form-group">
                    <strong>Featured Image:</strong>
                    <div class="image-preview">
                        <img src="../<?php echo $event['featured_image']; ?>" alt="Featured image" style="max-width: 300px;">
                        <p style="font-size: 12px; color: #666; margin-top: 5px;">Path: <?php echo $event['featured_image']; ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($event['gallery_images']) && is_array($event['gallery_images'])): ?>
                <div class="form-group">
                    <strong>Gallery Images:</strong>
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px;">
                        <?php foreach ($event['gallery_images'] as $index => $image): ?>
                        <div style="width: 200px;">
                            <img src="../<?php echo $image; ?>" alt="Gallery image" style="width: 100%; border-radius: 8px;">
                            <?php if (!empty($event['gallery_captions'][$index])): ?>
                            <p style="font-size: 12px; margin-top: 5px;"><?php echo htmlspecialchars($event['gallery_captions'][$index]); ?></p>
                            <?php endif; ?>
                            <p style="font-size: 10px; color: #999;">Path: <?php echo $image; ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <!-- Events List View -->
            <div class="action-bar">
                <div>
                    <a href="events.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Event
                    </a>
                </div>
                
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>">
                    <select id="statusFilter">
                        <option value="">All Status</option>
                        <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <button class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($events_result->num_rows > 0): ?>
                            <?php while ($event = $events_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($event['featured_image'])): ?>
                                    <img src="../<?php echo $event['featured_image']; ?>" class="event-image" alt="Event image">
                                    <?php else: ?>
                                    <div class="event-image" style="background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-calendar-alt" style="font-size: 24px; color: #ccc;"></i>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                    <?php if (!empty($event['description'])): ?>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars(substr($event['description'], 0, 50)) . '...'; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($event['event_date'])); ?></td>
                                <td><?php echo htmlspecialchars($event['location'] ?: '—'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $event['status']; ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($event['featured']): ?>
                                    <i class="fas fa-star" style="color: #ffc107;"></i> Yes
                                    <?php else: ?>
                                    —
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="events.php?action=view&id=<?php echo $event['id']; ?>" class="action-btn action-btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="events.php?action=edit&id=<?php echo $event['id']; ?>" class="action-btn action-btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="events.php?delete=<?php echo $event['id']; ?>" class="action-btn action-btn-delete" 
                                       onclick="return confirm('Are you sure you want to delete this event? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <i class="fas fa-calendar-alt" style="font-size: 48px; color: #ccc; margin-bottom: 15px; display: block;"></i>
                                    <p>No events found. Click "Add New Event" to create your first event.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?p=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.style.display = 'none';
                    }
                }, 500);
            });
        }, 5000);
        
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            let url = 'events.php?';
            if (search) url += 'search=' + encodeURIComponent(search);
            if (status) url += (search ? '&' : '') + 'status=' + encodeURIComponent(status);
            window.location.href = url || 'events.php';
        }
        
        // Handle Enter key in search input
        document.getElementById('searchInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        // Image preview for featured image
        document.getElementById('featured_image')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.image-preview');
                    if (preview) {
                        preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="max-width: 300px; border-radius: 8px;">';
                    }
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Gallery images preview
        document.getElementById('gallery_images')?.addEventListener('change', function(e) {
            const files = e.target.files;
            const previewContainer = document.createElement('div');
            previewContainer.style.marginTop = '10px';
            previewContainer.style.display = 'flex';
            previewContainer.style.flexWrap = 'wrap';
            previewContainer.style.gap = '10px';
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.width = '100px';
                    img.style.height = '100px';
                    img.style.objectFit = 'cover';
                    img.style.borderRadius = '8px';
                    previewContainer.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
            
            const existingPreview = this.nextElementSibling;
            if (existingPreview && existingPreview.classList?.contains('gallery-preview')) {
                existingPreview.remove();
            }
            previewContainer.classList.add('gallery-preview');
            this.parentNode.insertBefore(previewContainer, this.nextSibling);
        });
    </script>
</body>
</html>