<?php
/**
 * Manage Posts - Admin Panel
 * Mobile responsive with full CRUD operations and featured post management
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

// Handle post deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $post_id = (int)$_GET['delete'];
    
    // Get post image to delete file
    $stmt = $conn->prepare("SELECT featured_image FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    
    // Delete featured image if exists
    if (!empty($post['featured_image']) && file_exists('../uploads/images/' . $post['featured_image'])) {
        unlink('../uploads/images/' . $post['featured_image']);
    }
    
    // Delete post from database
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    
    if ($stmt->execute()) {
        $success_message = 'Post deleted successfully!';
        logAdminAction('delete_post', 'Deleted post ID: ' . $post_id);
    } else {
        $error_message = 'Failed to delete post.';
    }
    $stmt->close();
}

// Handle status toggle (draft/published)
if (isset($_GET['toggle']) && isset($_GET['status']) && is_numeric($_GET['toggle'])) {
    $post_id = (int)$_GET['toggle'];
    $new_status = $_GET['status'] == 'published' ? 'published' : 'draft';
    
    $stmt = $conn->prepare("UPDATE posts SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $post_id);
    
    if ($stmt->execute()) {
        $success_message = 'Post status updated to ' . $new_status . '!';
        logAdminAction('update_post_status', 'Updated post ID: ' . $post_id . ' to ' . $new_status);
    } else {
        $error_message = 'Failed to update post status.';
    }
    $stmt->close();
}

// Handle featured post toggle
if (isset($_GET['toggle_featured']) && is_numeric($_GET['toggle_featured'])) {
    $post_id = (int)$_GET['toggle_featured'];
    
    // Get current featured status
    $stmt = $conn->prepare("SELECT is_featured FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    
    if ($post) {
        if ($post['is_featured'] == 1) {
            // Remove featured status
            $stmt = $conn->prepare("UPDATE posts SET is_featured = 0 WHERE id = ?");
            $stmt->bind_param("i", $post_id);
            if ($stmt->execute()) {
                $success_message = 'Featured status removed from post.';
                logAdminAction('remove_featured', 'Removed featured status from post ID: ' . $post_id);
            } else {
                $error_message = 'Failed to remove featured status.';
            }
        } else {
            // Set as featured (remove from all others first)
            $conn->query("UPDATE posts SET is_featured = 0 WHERE is_featured = 1");
            $stmt = $conn->prepare("UPDATE posts SET is_featured = 1 WHERE id = ?");
            $stmt->bind_param("i", $post_id);
            if ($stmt->execute()) {
                $success_message = 'Post set as featured successfully!';
                logAdminAction('set_featured', 'Set post ID: ' . $post_id . ' as featured');
            } else {
                $error_message = 'Failed to set featured status.';
            }
        }
    }
    $stmt->close();
}

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['post_ids']) && is_array($_POST['post_ids'])) {
    $post_ids = array_map('intval', $_POST['post_ids']);
    $bulk_action = $_POST['bulk_action'];
    
    if ($bulk_action === 'delete') {
        foreach ($post_ids as $post_id) {
            // Get and delete featured image
            $stmt = $conn->prepare("SELECT featured_image FROM posts WHERE id = ?");
            $stmt->bind_param("i", $post_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $post = $result->fetch_assoc();
            
            if (!empty($post['featured_image']) && file_exists('../uploads/images/' . $post['featured_image'])) {
                unlink('../uploads/images/' . $post['featured_image']);
            }
            
            $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
            $stmt->bind_param("i", $post_id);
            $stmt->execute();
        }
        $success_message = count($post_ids) . ' post(s) deleted successfully!';
        logAdminAction('bulk_delete', 'Deleted ' . count($post_ids) . ' posts');
    } elseif ($bulk_action === 'publish') {
        $ids = implode(',', $post_ids);
        $conn->query("UPDATE posts SET status = 'published' WHERE id IN ($ids)");
        $success_message = count($post_ids) . ' post(s) published successfully!';
    } elseif ($bulk_action === 'draft') {
        $ids = implode(',', $post_ids);
        $conn->query("UPDATE posts SET status = 'draft' WHERE id IN ($ids)");
        $success_message = count($post_ids) . ' post(s) moved to draft!';
    } elseif ($bulk_action === 'featured') {
        // Remove featured from all posts first
        $conn->query("UPDATE posts SET is_featured = 0 WHERE is_featured = 1");
        // Set selected posts as featured (only first one)
        $first_id = $post_ids[0];
        $conn->query("UPDATE posts SET is_featured = 1 WHERE id = $first_id");
        $success_message = 'Post set as featured successfully!';
        logAdminAction('bulk_featured', 'Set post ID: ' . $first_id . ' as featured');
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$featured_filter = isset($_GET['featured']) ? $_GET['featured'] : '';

// Build query
$query = "SELECT p.*, u.username as author_name FROM posts p LEFT JOIN users u ON p.author_id = u.id WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM posts WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ?)";
    $count_query .= " AND (title LIKE ? OR content LIKE ? OR excerpt LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

if (!empty($status_filter)) {
    $query .= " AND p.status = ?";
    $count_query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($featured_filter === 'featured') {
    $query .= " AND p.is_featured = 1";
    $count_query .= " AND is_featured = 1";
} elseif ($featured_filter === 'not_featured') {
    $query .= " AND p.is_featured = 0";
    $count_query .= " AND is_featured = 0";
}

// Get total count for pagination
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_posts = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_posts / $per_page);

// Get posts for current page
$query .= " ORDER BY p.is_featured DESC, p.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$per_page, $offset]));
} else {
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$posts = $stmt->get_result();

// Get featured post count
$featured_count_result = $conn->query("SELECT COUNT(*) as count FROM posts WHERE is_featured = 1");
$featured_count = $featured_count_result->fetch_assoc()['count'];

// Get site settings for header
$site_title = getSetting('site_title', 'Refreshing Dews');
$site_logo = getSetting('site_logo', 'assets/logo/refreshing-dews-logo.png');
$primary_color = getSetting('primary_color', '#4a7c59');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Manage Posts - Admin Panel</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
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
            transition: all 0.3s;
        }
        
        .mobile-menu-toggle:active {
            transform: scale(0.95);
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
        
        /* Main Content Area */
        .admin-main {
            flex: 1;
            min-height: 100vh;
            padding: 30px;
            background: #f4f6f9;
            transition: margin-left 0.3s ease;
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
        
        /* Content Area */
        .content-area {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        @media (max-width: 768px) {
            .content-area {
                padding: 20px 15px;
            }
        }
        
        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .action-bar h2 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .featured-badge-count {
            background: #ffc107;
            color: #333;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
            font-family: 'Inter', sans-serif;
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
            background: #f8f9fa;
            color: #666;
            border: 1px solid #e0e0e0;
        }
        
        .btn-secondary:hover,
        .btn-secondary:active {
            background: #e9ecef;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover,
        .btn-danger:active {
            background: #c82333;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        /* Search and Filter */
        .filter-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
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
            font-size: 14px;
        }
        
        .search-box input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: <?php echo $primary_color; ?>;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
        }
        
        @media (max-width: 768px) {
            .filter-section {
                flex-direction: column;
            }
            .search-box, .filter-select {
                width: 100%;
            }
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
        
        .bulk-actions.hidden {
            display: none;
        }
        
        .bulk-select-all {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .bulk-select-all input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .bulk-select-all label {
            font-size: 13px;
            color: #666;
            cursor: pointer;
        }
        
        /* Messages */
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
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            margin: 0 -5px;
            padding: 0 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }
        
        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #555;
            font-weight: 600;
            font-size: 13px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
            font-size: 13px;
            vertical-align: middle;
        }
        
        tr:hover td {
            background: #f8f9fa;
        }
        
        .checkbox-col {
            width: 40px;
            text-align: center;
        }
        
        .checkbox-col input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .post-image {
            width: 45px;
            height: 45px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .post-title {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .post-slug {
            font-size: 11px;
            color: #999;
            margin-top: 3px;
            word-break: break-all;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-published {
            background: #d4edda;
            color: #155724;
        }
        
        .status-draft {
            background: #fff3cd;
            color: #856404;
        }
        
        .featured-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #ffc107;
            color: #333;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .featured-badge i {
            margin-right: 3px;
            font-size: 10px;
        }
        
        .action-links {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .action-link {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 13px;
        }
        
        .action-link:active {
            transform: scale(0.95);
        }
        
        .action-link.edit {
            background: <?php echo $primary_color; ?>;
        }
        
        .action-link.edit:hover,
        .action-link.edit:active {
            background: #2c4a3b;
        }
        
        .action-link.view {
            background: #17a2b8;
        }
        
        .action-link.view:hover,
        .action-link.view:active {
            background: #138496;
        }
        
        .action-link.toggle {
            background: #ffc107;
            color: #333;
        }
        
        .action-link.toggle:hover,
        .action-link.toggle:active {
            background: #e0a800;
        }
        
        .action-link.featured {
            background: #ffc107;
            color: #333;
        }
        
        .action-link.featured i {
            color: #ffc107;
            text-shadow: 0 0 2px rgba(0,0,0,0.3);
        }
        
        .action-link.not-featured {
            background: #6c757d;
            color: white;
        }
        
        .action-link.delete {
            background: #dc3545;
        }
        
        .action-link.delete:hover,
        .action-link.delete:active {
            background: #c82333;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
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
            min-width: 38px;
            height: 38px;
            padding: 0 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s;
        }
        
        .page-link:hover,
        .page-item.active .page-link {
            background: <?php echo $primary_color; ?>;
            border-color: <?php echo $primary_color; ?>;
            color: white;
        }
        
        .page-item.disabled .page-link {
            opacity: 0.5;
            pointer-events: none;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
        }
        
        .empty-state i {
            font-size: 60px;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 18px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        /* Touch-friendly */
        @media (hover: none) and (pointer: coarse) {
            .btn:active,
            .action-link:active,
            .sidebar-menu-item:active {
                transform: scale(0.98);
            }
        }
        
        /* Print */
        @media print {
            .admin-sidebar,
            .mobile-menu-toggle,
            .sidebar-overlay,
            .action-buttons,
            .filter-section,
            .bulk-actions,
            .action-links {
                display: none;
            }
            .admin-main {
                margin: 0;
                padding: 0;
            }
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
                    <h1>Blog Posts</h1>
                    <p>Manage your blog content</p>
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
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Action Bar -->
                <div class="action-bar">
                    <h2>
                        All Posts (<?php echo $total_posts; ?>)
                        <?php if ($featured_count > 0): ?>
                        <span class="featured-badge-count">
                            <i class="fas fa-star"></i> <?php echo $featured_count; ?> Featured
                        </span>
                        <?php endif; ?>
                    </h2>
                    <div class="action-buttons">
                        <a href="add-post.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Post
                        </a>
                    </div>
                </div>
                
                <!-- Messages -->
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
                
                <!-- Search and Filter -->
                <div class="filter-section">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search posts..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="published" <?php echo $status_filter == 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                    <select class="filter-select" id="featuredFilter">
                        <option value="">All Posts</option>
                        <option value="featured" <?php echo $featured_filter == 'featured' ? 'selected' : ''; ?>>Featured Posts</option>
                        <option value="not_featured" <?php echo $featured_filter == 'not_featured' ? 'selected' : ''; ?>>Not Featured</option>
                    </select>
                    <button class="btn btn-primary" onclick="applyFilters()">Apply</button>
                    <a href="posts.php" class="btn btn-secondary">Clear</a>
                </div>
                
                <!-- Bulk Actions -->
                <form method="POST" action="" id="bulkForm">
                    <div class="bulk-actions" id="bulkActions">
                        <div class="bulk-select-all">
                            <input type="checkbox" id="selectAll">
                            <label for="selectAll">Select All</label>
                        </div>
                        <select name="bulk_action" class="filter-select" style="width: auto;">
                            <option value="">Bulk Actions</option>
                            <option value="publish">Publish</option>
                            <option value="draft">Move to Draft</option>
                            <option value="featured">Set as Featured</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirmBulkAction()">Apply</button>
                        <span id="selectedCount" style="font-size: 12px; color: #666;"></span>
                    </div>
                
                    <!-- Posts Table -->
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th class="checkbox-col"><input type="checkbox" id="selectAllTable"></th>
                                    <th>Image</th>
                                    <th>Title</th>
                                    <th>Author</th>
                                    <th>Date</th>
                                    <th>Views</th>
                                    <th>Status</th>
                                    <th>Featured</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($posts->num_rows > 0): ?>
                                    <?php while ($post = $posts->fetch_assoc()): ?>
                                    <tr>
                                        <td class="checkbox-col">
                                            <input type="checkbox" name="post_ids[]" value="<?php echo $post['id']; ?>" class="post-checkbox">
                                        </td>
                                        <td>
                                            <img src="<?php echo !empty($post['featured_image']) ? '../uploads/images/' . $post['featured_image'] : '../assets/images/default-post.jpg'; ?>" 
                                                 alt="<?php echo htmlspecialchars($post['title']); ?>" 
                                                 class="post-image"
                                                 loading="lazy">
                                        </td>
                                        <td>
                                            <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                                            <div class="post-slug"><?php echo htmlspecialchars($post['slug']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($post['author_name'] ?? 'Unknown'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                                        <td><?php echo number_format($post['views']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $post['status']; ?>">
                                                <?php echo ucfirst($post['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($post['is_featured'] == 1): ?>
                                                <span class="featured-badge">
                                                    <i class="fas fa-star"></i> Featured
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-links">
                                                <a href="add-post.php?id=<?php echo $post['id']; ?>" class="action-link edit" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="../blog-post.php?slug=<?php echo urlencode($post['slug']); ?>" class="action-link view" title="View" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?toggle=<?php echo $post['id']; ?>&status=<?php echo $post['status'] == 'published' ? 'draft' : 'published'; ?>" 
                                                   class="action-link toggle" 
                                                   title="<?php echo $post['status'] == 'published' ? 'Move to Draft' : 'Publish'; ?>"
                                                   onclick="return confirm('Change status to <?php echo $post['status'] == 'published' ? 'draft' : 'published'; ?>?')">
                                                    <i class="fas fa-<?php echo $post['status'] == 'published' ? 'eye-slash' : 'eye'; ?>"></i>
                                                </a>
                                                <a href="?toggle_featured=<?php echo $post['id']; ?>" 
                                                   class="action-link <?php echo $post['is_featured'] ? 'featured' : 'not-featured'; ?>"
                                                   title="<?php echo $post['is_featured'] ? 'Remove from featured' : 'Set as featured'; ?>"
                                                   onclick="return confirm('<?php echo $post['is_featured'] ? 'Remove this post from featured?' : 'Set this post as featured? This will replace the current featured post.'; ?>')">
                                                    <i class="fas fa-<?php echo $post['is_featured'] ? 'star' : 'star-o'; ?>"></i>
                                                </a>
                                                <a href="?delete=<?php echo $post['id']; ?>" 
                                                   class="action-link delete" 
                                                   title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" style="text-align: center; padding: 50px;">
                                            <div class="empty-state">
                                                <i class="fas fa-newspaper"></i>
                                                <h3>No Posts Found</h3>
                                                <p>Get started by creating your first blog post.</p>
                                                <a href="add-post.php" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Create First Post
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&featured=<?php echo $featured_filter; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&featured=<?php echo $featured_filter; ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&featured=<?php echo $featured_filter; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&featured=<?php echo $featured_filter; ?>">
                                <?php echo $total_pages; ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&featured=<?php echo $featured_filter; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
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
        
        // Apply filters function
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            const featured = document.getElementById('featuredFilter').value;
            let url = 'posts.php?';
            if (search) url += 'search=' + encodeURIComponent(search) + '&';
            if (status) url += 'status=' + encodeURIComponent(status) + '&';
            if (featured) url += 'featured=' + encodeURIComponent(featured) + '&';
            window.location.href = url.slice(0, -1);
        }
        
        // Enter key in search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => {
                    if (alert.parentNode) alert.style.display = 'none';
                }, 500);
            }, 5000);
        });
        
        // Bulk actions functionality
        const selectAllCheckbox = document.getElementById('selectAll');
        const selectAllTableCheckbox = document.getElementById('selectAllTable');
        const postCheckboxes = document.querySelectorAll('.post-checkbox');
        const bulkActionsDiv = document.getElementById('bulkActions');
        const selectedCountSpan = document.getElementById('selectedCount');
        
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.post-checkbox:checked');
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
            const allChecked = postCheckboxes.length > 0 && 
                Array.from(postCheckboxes).every(cb => cb.checked);
            if (selectAllCheckbox) selectAllCheckbox.checked = allChecked;
            if (selectAllTableCheckbox) selectAllTableCheckbox.checked = allChecked;
        }
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                postCheckboxes.forEach(cb => cb.checked = this.checked);
                if (selectAllTableCheckbox) selectAllTableCheckbox.checked = this.checked;
                updateSelectedCount();
            });
        }
        
        if (selectAllTableCheckbox) {
            selectAllTableCheckbox.addEventListener('change', function() {
                postCheckboxes.forEach(cb => cb.checked = this.checked);
                if (selectAllCheckbox) selectAllCheckbox.checked = this.checked;
                updateSelectedCount();
            });
        }
        
        postCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                updateSelectedCount();
                updateSelectAll();
            });
        });
        
        function confirmBulkAction() {
            const action = document.querySelector('select[name="bulk_action"]').value;
            const checked = document.querySelectorAll('.post-checkbox:checked');
            
            if (checked.length === 0) {
                alert('Please select at least one post.');
                return false;
            }
            
            if (action === 'delete') {
                return confirm('Are you sure you want to delete ' + checked.length + ' post(s)? This action cannot be undone.');
            } else if (action === 'publish') {
                return confirm('Publish ' + checked.length + ' selected post(s)?');
            } else if (action === 'draft') {
                return confirm('Move ' + checked.length + ' selected post(s) to draft?');
            } else if (action === 'featured') {
                if (checked.length > 1) {
                    alert('Only one post can be featured. The first selected post will be set as featured.');
                }
                return confirm('Set the selected post as featured? This will replace any existing featured post.');
            } else if (action === '') {
                alert('Please select a bulk action.');
                return false;
            }
            
            return true;
        }
        
        updateSelectedCount();
        
        // Touch-friendly: Add active state feedback
        if ('ontouchstart' in window) {
            document.querySelectorAll('.btn, .action-link').forEach(el => {
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