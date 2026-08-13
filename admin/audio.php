<?php
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

// Handle audio deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $audio_id = (int)$_GET['delete'];
    
    // Get audio file info
    $stmt = $conn->prepare("SELECT audio_file, cover_image FROM audio_messages WHERE id = ?");
    $stmt->bind_param("i", $audio_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $audio = $result->fetch_assoc();
    $stmt->close();
    
    // Delete audio file if exists
    if (!empty($audio['audio_file']) && file_exists('../' . $audio['audio_file'])) {
        unlink('../' . $audio['audio_file']);
    }
    
    // Delete cover image if exists
    if (!empty($audio['cover_image']) && file_exists('../uploads/images/' . $audio['cover_image'])) {
        unlink('../uploads/images/' . $audio['cover_image']);
    }
    
    // Delete from database
    $stmt = $conn->prepare("DELETE FROM audio_messages WHERE id = ?");
    $stmt->bind_param("i", $audio_id);
    
    if ($stmt->execute()) {
        $success_message = 'Audio message deleted successfully!';
        logAdminAction('delete_audio', 'Deleted audio ID: ' . $audio_id);
    } else {
        $error_message = 'Failed to delete audio message.';
    }
    $stmt->close();
}

// Handle status toggle (draft/published)
if (isset($_GET['toggle']) && isset($_GET['status']) && is_numeric($_GET['toggle'])) {
    $audio_id = (int)$_GET['toggle'];
    $new_status = $_GET['status'] == 'published' ? 'published' : 'draft';
    
    $stmt = $conn->prepare("UPDATE audio_messages SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $audio_id);
    
    if ($stmt->execute()) {
        $success_message = 'Audio status updated to ' . $new_status . '!';
        logAdminAction('update_audio_status', 'Updated audio ID: ' . $audio_id . ' to ' . $new_status);
    } else {
        $error_message = 'Failed to update audio status.';
    }
    $stmt->close();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$query = "SELECT a.*, u.username FROM audio_messages a 
          LEFT JOIN users u ON a.author_id = u.id 
          WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM audio_messages WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
    $count_query .= " AND (title LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term]);
    $types .= "ss";
}

if (!empty($status_filter)) {
    $query .= " AND a.status = ?";
    $count_query .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Get total count for pagination
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_audio = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_audio / $per_page);
$stmt->close();

// Get audio messages for current page
$query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types . "ii", ...array_merge($params, [$per_page, $offset]));
} else {
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$audio_messages = $stmt->get_result();
$stmt->close();

// Get total plays count
$plays_stmt = $conn->query("SELECT SUM(plays) as total_plays FROM audio_messages");
$total_plays = $plays_stmt->fetch_assoc()['total_plays'] ?? 0;
$plays_stmt->close();

// Get counts by status
$status_counts = [];
$counts_stmt = $conn->query("SELECT status, COUNT(*) as count FROM audio_messages GROUP BY status");
while ($row = $counts_stmt->fetch_assoc()) {
    $status_counts[$row['status']] = $row['count'];
}
$published_count = $status_counts['published'] ?? 0;
$draft_count = $status_counts['draft'] ?? 0;
$counts_stmt->close();

// Function to format duration
function formatDuration($seconds) {
    if (empty($seconds)) return '--:--';
    
    $minutes = floor($seconds / 60);
    $remaining_seconds = $seconds % 60;
    
    if ($minutes < 60) {
        return sprintf("%d:%02d", $minutes, $remaining_seconds);
    } else {
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        return sprintf("%d:%02d:%02d", $hours, $remaining_minutes, $remaining_seconds);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audio Messages - Admin Panel</title>
    
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
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
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
            color: rgba(255,255,255,0.9);
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
            font-size: 18px;
        }
        
        .sidebar-menu-item:hover,
        .sidebar-menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #4a7c59;
        }
        
        .sidebar-menu-item.active {
            background: rgba(74, 124, 89, 0.2);
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
        
        /* Main Content Area */
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: #f4f6f9;
            min-height: 100vh;
        }
        
        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 15px 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        /* Content Area */
        .content-area {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card:nth-child(4) {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }
        
        .stat-card i {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 48px;
            opacity: 0.3;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 16px;
            opacity: 0.9;
        }
        
        /* Action Bar */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .action-bar h2 {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #4a7c59;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2c4a3b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(74, 124, 89, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: white;
            color: #666;
            border: 2px solid #e0e0e0;
        }
        
        .btn-outline:hover {
            background: #f8f9fa;
            border-color: #999;
        }
        
        /* Search and Filter */
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #4a7c59;
        }
        
        .filter-select {
            padding: 12px 25px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            min-width: 150px;
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
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: #555;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
            font-size: 14px;
        }
        
        tr:hover td {
            background: #f8f9fa;
        }
        
        .audio-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .audio-cover {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            background: linear-gradient(135deg, #4a7c59, #2c4a3b);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .audio-details {
            flex: 1;
        }
        
        .audio-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }
        
        .audio-meta {
            font-size: 12px;
            color: #999;
            display: flex;
            gap: 10px;
        }
        
        .audio-meta i {
            margin-right: 3px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
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
        
        .plays-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .action-links {
            display: flex;
            gap: 8px;
        }
        
        .action-link {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .action-link:hover {
            transform: translateY(-3px);
        }
        
        .action-link.edit {
            background: #4a7c59;
        }
        
        .action-link.edit:hover {
            background: #2c4a3b;
        }
        
        .action-link.view {
            background: #17a2b8;
        }
        
        .action-link.view:hover {
            background: #138496;
        }
        
        .action-link.toggle {
            background: #ffc107;
            color: #333;
        }
        
        .action-link.toggle:hover {
            background: #e0a800;
        }
        
        .action-link.delete {
            background: #dc3545;
        }
        
        .action-link.delete:hover {
            background: #c82333;
        }
        
        .action-link.play {
            background: #28a745;
        }
        
        .action-link.play:hover {
            background: #218838;
        }
        
        /* Audio Player Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            font-size: 20px;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .audio-player-info {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .audio-player-cover {
            width: 150px;
            height: 150px;
            border-radius: 15px;
            margin: 0 auto 15px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .audio-player-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .audio-player-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .audio-player-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            max-height: 100px;
            overflow-y: auto;
        }
        
        .audio-player {
            width: 100%;
            margin: 15px 0;
        }
        
        .audio-player audio {
            width: 100%;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
            list-style: none;
        }
        
        .page-item {
            display: inline-block;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            color: #666;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .page-link:hover,
        .page-item.active .page-link {
            background: #4a7c59;
            border-color: #4a7c59;
            color: white;
        }
        
        .page-item.disabled .page-link {
            opacity: 0.5;
            pointer-events: none;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 20px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #999;
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .admin-sidebar {
                width: 80px;
            }
            
            .sidebar-header h3,
            .sidebar-header p,
            .sidebar-menu-item span,
            .sidebar-menu-label {
                display: none;
            }
            
            .sidebar-header img {
                max-width: 50px;
                padding: 5px;
            }
            
            .admin-main {
                margin-left: 80px;
            }
        }
        
        @media (max-width: 768px) {
            .admin-main {
                margin-left: 0;
                padding: 15px;
            }
            
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-section {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .audio-info {
                flex-direction: column;
                text-align: center;
            }
            
            .audio-cover {
                margin: 0 auto;
            }
            
            .action-links {
                justify-content: center;
            }
            
            th, td {
                padding: 10px;
            }
            
            table {
                font-size: 13px;
            }
            
            /* Hide some columns on mobile */
            th:nth-child(3),
            td:nth-child(3),
            th:nth-child(5),
            td:nth-child(5) {
                display: none;
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
                    <h1>Audio Messages</h1>
                    <p>Manage your audio content</p>
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
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-headphones"></i>
                        <div class="stat-value"><?php echo $total_audio; ?></div>
                        <div class="stat-label">Total Audio</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-check-circle"></i>
                        <div class="stat-value"><?php echo $published_count; ?></div>
                        <div class="stat-label">Published</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-clock"></i>
                        <div class="stat-value"><?php echo $draft_count; ?></div>
                        <div class="stat-label">Drafts</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-play-circle"></i>
                        <div class="stat-value"><?php echo number_format($total_plays); ?></div>
                        <div class="stat-label">Total Plays</div>
                    </div>
                </div>
                
                <!-- Action Bar -->
                <div class="action-bar">
                    <h2>All Audio Messages (<?php echo $total_audio; ?>)</h2>
                    <div class="action-buttons">
                        <a href="add-audio.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Audio
                        </a>
                        <a href="?export=csv" class="btn btn-outline">
                            <i class="fas fa-download"></i> Export
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
                        <input type="text" id="searchInput" placeholder="Search audio messages..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select class="filter-select" id="statusFilter">
                        <option value="">All Status</option>
                        <option value="published" <?php echo $status_filter == 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="draft" <?php echo $status_filter == 'draft' ? 'selected' : ''; ?>>Draft</option>
                    </select>
                    <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                    <a href="audio.php" class="btn btn-outline">Clear</a>
                </div>
                
                <!-- Audio Table -->
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Audio</th>
                                <th>Title & Description</th>
                                <th>Duration</th>
                                <th>Plays</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($audio_messages->num_rows > 0): ?>
                                <?php while ($audio = $audio_messages->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="audio-info">
                                            <?php if (!empty($audio['cover_image'])): ?>
                                                <img src="../uploads/images/<?php echo $audio['cover_image']; ?>" 
                                                     alt="<?php echo htmlspecialchars($audio['title']); ?>" 
                                                     class="audio-cover">
                                            <?php else: ?>
                                                <div class="audio-cover">
                                                    <i class="fas fa-headphones"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="audio-details">
                                            <div class="audio-title"><?php echo htmlspecialchars($audio['title']); ?></div>
                                            <div class="audio-meta">
                                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($audio['username'] ?? 'Unknown'); ?></span>
                                                <?php if (!empty($audio['duration'])): ?>
                                                <span><i class="far fa-clock"></i> <?php echo $audio['duration']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($audio['description'])): ?>
                                                <div class="audio-meta" style="margin-top: 5px;">
                                                    <?php echo substr(htmlspecialchars($audio['description']), 0, 100) . '...'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $audio['duration'] ?? '--:--'; ?></td>
                                    <td>
                                        <span class="plays-badge">
                                            <i class="fas fa-play"></i> <?php echo number_format($audio['plays'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($audio['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $audio['status']; ?>">
                                            <?php echo ucfirst($audio['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-links">
                                            <button type="button" class="action-link play" title="Play" 
                                                    onclick='playAudio(<?php echo json_encode([
                                                        'title' => $audio['title'],
                                                        'description' => $audio['description'],
                                                        'audio_file' => $audio['audio_file'],
                                                        'cover_image' => $audio['cover_image']
                                                    ]); ?>)'>
                                                <i class="fas fa-play"></i>
                                            </button>
                                            
                                            <a href="edit-audio.php?id=<?php echo $audio['id']; ?>" class="action-link edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            
                                            <a href="?toggle=<?php echo $audio['id']; ?>&status=<?php echo $audio['status'] == 'published' ? 'draft' : 'published'; ?>" 
                                               class="action-link toggle" 
                                               title="<?php echo $audio['status'] == 'published' ? 'Move to Draft' : 'Publish'; ?>"
                                               onclick="return confirm('Change status to <?php echo $audio['status'] == 'published' ? 'draft' : 'published'; ?>?')">
                                                <i class="fas fa-<?php echo $audio['status'] == 'published' ? 'eye-slash' : 'eye'; ?>"></i>
                                            </a>
                                            
                                            <a href="?delete=<?php echo $audio['id']; ?>" 
                                               class="action-link delete" 
                                               title="Delete"
                                               onclick="return confirm('Are you sure you want to delete this audio message? This action cannot be undone.')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <i class="fas fa-headphones"></i>
                                            <h3>No Audio Messages Found</h3>
                                            <p>Get started by adding your first audio message.</p>
                                            <a href="add-audio.php" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Add New Audio
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <ul class="pagination">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Audio Player Modal -->
    <div class="modal" id="audioPlayerModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalAudioTitle">Audio Player</h3>
                <button class="modal-close" onclick="closeAudioPlayer()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="audio-player-info">
                    <div class="audio-player-cover" id="modalAudioCover">
                        <img src="../assets/images/default-audio.jpg" alt="Audio Cover">
                    </div>
                    <div class="audio-player-title" id="modalAudioDisplayTitle"></div>
                    <div class="audio-player-description" id="modalAudioDescription"></div>
                </div>
                <div class="audio-player">
                    <audio id="audioElement" controls controlsList="nodownload">
                        <source id="audioSource" src="" type="audio/mpeg">
                        Your browser does not support the audio element.
                    </audio>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Apply filters function
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            window.location.href = 'audio.php?search=' + encodeURIComponent(search) + '&status=' + encodeURIComponent(status);
        }
        
        // Enter key in search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        // Audio player
        function playAudio(audioData) {
            // Set modal content
            document.getElementById('modalAudioTitle').textContent = audioData.title;
            document.getElementById('modalAudioDisplayTitle').textContent = audioData.title;
            document.getElementById('modalAudioDescription').textContent = audioData.description || 'No description available.';
            
            // Set cover image
            const coverImg = document.querySelector('#modalAudioCover img');
            if (audioData.cover_image) {
                coverImg.src = '../uploads/images/' + audioData.cover_image;
            } else {
                coverImg.src = '../assets/images/default-audio.jpg';
            }
            
            // Set audio source
            const audioSource = document.getElementById('audioSource');
            const audioElement = document.getElementById('audioElement');
            
            if (audioData.audio_file) {
                // Check if the path already includes 'uploads/audio/'
                if (audioData.audio_file.includes('uploads/audio/')) {
                    audioSource.src = '../' + audioData.audio_file;
                } else {
                    audioSource.src = '../uploads/audio/' + audioData.audio_file;
                }
            }
            
            audioElement.load();
            
            // Show modal
            document.getElementById('audioPlayerModal').classList.add('show');
            
            // Auto play
            setTimeout(() => {
                audioElement.play().catch(e => {
                    console.log('Auto-play prevented:', e);
                });
            }, 100);
        }
        
        function closeAudioPlayer() {
            const audio = document.getElementById('audioElement');
            audio.pause();
            audio.currentTime = 0;
            document.getElementById('audioPlayerModal').classList.remove('show');
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('audioPlayerModal').classList.contains('show')) {
                closeAudioPlayer();
            }
        });
        
        // Click outside modal to close
        document.getElementById('audioPlayerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAudioPlayer();
            }
        });
        
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
        
        // Preview audio duration on hover
        document.querySelectorAll('.action-link.play').forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                // Could add tooltip with duration here
            });
        });
    </script>
</body>
</html>