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

// Initialize stats array with default values
$stats = [
    'total_posts' => 0,
    'total_audio' => 0,
    'total_views' => 0,
    'total_plays' => 0
];

// Get site statistics safely
if (function_exists('getSiteStats')) {
    $stats = getSiteStats();
} else {
    // Fallback: query directly
    if (isset($conn) && !$conn->connect_error) {
        // Count posts
        $result = $conn->query("SELECT COUNT(*) as count FROM posts WHERE status = 'published'");
        if ($result) {
            $stats['total_posts'] = $result->fetch_assoc()['count'];
        }
        
        // Count audio
        $result = $conn->query("SELECT COUNT(*) as count FROM audio_messages WHERE status = 'published'");
        if ($result) {
            $stats['total_audio'] = $result->fetch_assoc()['count'];
        }
        
        // Sum views
        $result = $conn->query("SELECT SUM(views) as total FROM posts");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_views'] = $row['total'] ?? 0;
        }
        
        // Sum plays
        $result = $conn->query("SELECT SUM(plays) as total FROM audio_messages");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['total_plays'] = $row['total'] ?? 0;
        }
    }
}

// Get recent posts safely
$recent_posts = [];
if (function_exists('getRecentPosts')) {
    $recent_posts = getRecentPosts(5);
} else {
    if (isset($conn) && !$conn->connect_error) {
        $result = $conn->query("SELECT id, title, views, created_at FROM posts ORDER BY created_at DESC LIMIT 5");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_posts[] = $row;
            }
        }
    }
}

// Get recent audio safely
$recent_audio = [];
if (function_exists('getRecentAudio')) {
    $recent_audio = getRecentAudio(5);
} else {
    if (isset($conn) && !$conn->connect_error) {
        $result = $conn->query("SELECT id, title, plays, created_at FROM audio_messages ORDER BY created_at DESC LIMIT 5");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_audio[] = $row;
            }
        }
    }
}

// Get system info safely
$php_version = phpversion();
$mysql_version = isset($conn) && !$conn->connect_error ? $conn->server_info : 'Not connected';
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$upload_max_filesize = ini_get('upload_max_filesize');
$post_max_size = ini_get('post_max_size');
$memory_limit = ini_get('memory_limit');

// Get current date and time
$current_time = date('Y-m-d H:i:s');
$timezone = date_default_timezone_get();

// Handle quick actions
$action_message = '';
$action_error = '';

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'clear_cache':
            $cache_dir = '../cache/';
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '*');
                foreach($files as $file) {
                    if(is_file($file)) {
                        unlink($file);
                    }
                }
                $action_message = 'Cache cleared successfully!';
                if (function_exists('logAdminAction')) {
                    logAdminAction('clear_cache', 'Cache cleared by admin');
                }
            } else {
                $action_message = 'No cache directory found.';
            }
            break;
            
        case 'backup_settings':
            $backup_dir = '../backups/';
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            $backup_file = $backup_dir . 'settings_backup_' . date('Y-m-d_H-i-s') . '.json';
            
            $settings = [];
            if (isset($conn) && !$conn->connect_error) {
                $result = $conn->query("SELECT setting_key, setting_value FROM settings");
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
            
            if (file_put_contents($backup_file, json_encode($settings, JSON_PRETTY_PRINT))) {
                $action_message = 'Settings backup created successfully! File: ' . basename($backup_file);
                if (function_exists('logAdminAction')) {
                    logAdminAction('backup_settings', 'Settings backup created');
                }
            } else {
                $action_error = 'Failed to create backup.';
            }
            break;
    }
}

// Get flash message if any
$flash_message = null;
if (function_exists('getFlashMessage')) {
    $flash_message = getFlashMessage();
}

// Helper function for date formatting if not exists
if (!function_exists('formatDate')) {
    function formatDate($date) {
        return date('M j, Y', strtotime($date));
    }
}

// Helper function for getting settings if not exists
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Dashboard - Admin Panel</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        
        html, body {
            height: 100%;
            overflow-x: hidden;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f6f9;
            color: #333;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            position: relative;
        }
        
        /* Sidebar Styles - Mobile First Approach */
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
        
        /* Mobile Sidebar - Initially hidden */
        @media (max-width: 1024px) {
            .admin-sidebar {
                position: fixed;
                left: 0;
                top: 0;
                transform: translateX(-100%);
                z-index: 2000;
                width: 280px;
                height: 100vh;
            }
            
            .admin-sidebar.sidebar-open {
                transform: translateX(0);
                box-shadow: 2px 0 20px rgba(0,0,0,0.2);
            }
        }
        
        /* Custom scrollbar for sidebar */
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
            padding: 20px 0 40px 0;
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
            -webkit-tap-highlight-color: transparent;
        }
        
        .sidebar-menu-item i {
            width: 20px;
            font-size: 18px;
        }
        
        .sidebar-menu-item:active {
            transform: scale(0.98);
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
        
        /* Mobile Menu Toggle Button */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1500;
            background: #4a7c59;
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
        
        /* Overlay for mobile sidebar */
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
            overflow-x: hidden;
            padding: 30px;
            background: #f4f6f9;
            transition: margin-left 0.3s ease;
        }
        
        @media (max-width: 1024px) {
            .admin-main {
                padding: 80px 20px 20px 20px;
            }
        }
        
        @media (max-width: 768px) {
            .admin-main {
                padding: 80px 15px 15px 15px;
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
            
            .top-nav-user {
                width: 100%;
                justify-content: center;
            }
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
        
        @media (max-width: 768px) {
            .top-nav-title h1 {
                font-size: 20px;
            }
            .top-nav-title p {
                font-size: 12px;
            }
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
            flex-shrink: 0;
        }
        
        /* Stats Cards - Responsive Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                gap: 15px;
            }
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .stat-card:active {
            transform: scale(0.98);
        }
        
        @media (min-width: 1025px) {
            .stat-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            }
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #4a7c59, #2c4a3b);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(74, 124, 89, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        
        .stat-icon i {
            font-size: 24px;
            color: #4a7c59;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 13px;
        }
        
        .stat-change {
            margin-top: 8px;
            font-size: 12px;
            color: #28a745;
        }
        
        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .charts-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .chart-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .chart-header select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
            background: white;
            cursor: pointer;
        }
        
        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                height: 250px;
            }
        }
        
        /* Recent Activity */
        .recent-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .recent-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        .recent-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            max-height: 450px;
        }
        
        .recent-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-shrink: 0;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .recent-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .recent-header a {
            color: #4a7c59;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            padding: 5px 10px;
            border-radius: 6px;
            transition: background 0.3s;
        }
        
        .recent-header a:active {
            background: rgba(74, 124, 89, 0.1);
        }
        
        .recent-list {
            overflow-y: auto;
            flex: 1;
            padding-right: 5px;
        }
        
        .recent-list::-webkit-scrollbar {
            width: 4px;
        }
        
        .recent-list::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .recent-list::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }
        
        .recent-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .recent-icon {
            width: 40px;
            height: 40px;
            background: rgba(74, 124, 89, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4a7c59;
            flex-shrink: 0;
        }
        
        .recent-content {
            flex: 1;
            min-width: 0;
        }
        
        .recent-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 14px;
        }
        
        .recent-meta {
            font-size: 11px;
            color: #666;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .recent-meta i {
            margin-right: 3px;
        }
        
        .recent-edit {
            color: #4a7c59;
            text-decoration: none;
            flex-shrink: 0;
            padding: 8px;
            border-radius: 8px;
            transition: background 0.3s;
        }
        
        .recent-edit:active {
            background: rgba(74, 124, 89, 0.1);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 40px;
            margin-bottom: 15px;
            opacity: 0.5;
            color: #999;
        }
        
        .empty-state p {
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .empty-state .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #4a7c59;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .empty-state .btn:active {
            transform: scale(0.98);
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .quick-actions h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
        
        @media (max-width: 480px) {
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
        }
        
        .action-btn {
            padding: 12px;
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            display: block;
            cursor: pointer;
        }
        
        .action-btn:active {
            transform: scale(0.98);
            background: #e9ecef;
        }
        
        @media (min-width: 1025px) {
            .action-btn:hover {
                background: #4a7c59;
                color: white;
                transform: translateY(-3px);
                border-color: #4a7c59;
            }
        }
        
        .action-btn i {
            font-size: 20px;
            margin-bottom: 8px;
            display: block;
        }
        
        .action-btn span {
            font-size: 12px;
            font-weight: 500;
        }
        
        /* System Info */
        .system-info {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }
        
        .system-info h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .info-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            word-break: break-word;
        }
        
        /* Messages */
        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
            font-size: 14px;
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
        
        .alert i {
            font-size: 18px;
        }
        
        /* Touch-friendly adjustments */
        @media (hover: none) and (pointer: coarse) {
            .sidebar-menu-item,
            .action-btn,
            .stat-card,
            .recent-edit,
            .empty-state .btn {
                min-height: 44px;
            }
            
            .recent-edit {
                min-width: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
        
        /* Print styles */
        @media print {
            .admin-sidebar,
            .mobile-menu-toggle,
            .sidebar-overlay,
            .quick-actions,
            .system-info {
                display: none;
            }
            
            .admin-main {
                margin-left: 0;
                padding: 0;
            }
            
            .stat-card,
            .chart-card,
            .recent-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle Button -->
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
                    <h1>Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($admin_username); ?>! Here's what's happening today.</p>
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
            
            <!-- Flash Messages -->
            <?php if ($flash_message && is_array($flash_message) && isset($flash_message['message'])): ?>
            <div class="alert alert-<?php echo $flash_message['type'] ?? 'info'; ?>">
                <i class="fas fa-<?php echo ($flash_message['type'] ?? 'info') == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($flash_message['message']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Action Messages -->
            <?php if ($action_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($action_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($action_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($action_error); ?>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-pencil-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_posts']); ?></div>
                    <div class="stat-label">Total Blog Posts</div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> Published content
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-headphones"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_audio']); ?></div>
                    <div class="stat-label">Audio Messages</div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> Ready to listen
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_views']); ?></div>
                    <div class="stat-label">Total Views</div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> All time
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_plays']); ?></div>
                    <div class="stat-label">Audio Plays</div>
                    <div class="stat-change">
                        <i class="fas fa-arrow-up"></i> Total listens
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="charts-row">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Views Overview</h3>
                        <select id="viewsPeriod">
                            <option value="7">Last 7 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="viewsChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Content Distribution</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="recent-grid">
                <!-- Recent Posts -->
                <div class="recent-card">
                    <div class="recent-header">
                        <h3>Recent Blog Posts</h3>
                        <a href="posts.php">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="recent-list">
                        <?php if (empty($recent_posts)): ?>
                        <div class="empty-state">
                            <i class="fas fa-pencil-alt"></i>
                            <p>No blog posts yet.</p>
                            <a href="add-post.php" class="btn">
                                <i class="fas fa-plus"></i> Create First Post
                            </a>
                        </div>
                        <?php else: ?>
                            <?php foreach ($recent_posts as $post): ?>
                            <div class="recent-item">
                                <div class="recent-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="recent-content">
                                    <div class="recent-title"><?php echo htmlspecialchars($post['title'] ?? 'Untitled'); ?></div>
                                    <div class="recent-meta">
                                        <span><i class="far fa-calendar"></i> <?php echo isset($post['created_at']) ? formatDate($post['created_at']) : 'Unknown'; ?></span>
                                        <span><i class="far fa-eye"></i> <?php echo number_format($post['views'] ?? 0); ?> views</span>
                                    </div>
                                </div>
                                <a href="add-post.php?id=<?php echo $post['id'] ?? 0; ?>" class="recent-edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Audio -->
                <div class="recent-card">
                    <div class="recent-header">
                        <h3>Recent Audio Messages</h3>
                        <a href="audio.php">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    
                    <div class="recent-list">
                        <?php if (empty($recent_audio)): ?>
                        <div class="empty-state">
                            <i class="fas fa-headphones"></i>
                            <p>No audio messages yet.</p>
                            <a href="add-audio.php" class="btn">
                                <i class="fas fa-plus"></i> Upload Audio
                            </a>
                        </div>
                        <?php else: ?>
                            <?php foreach ($recent_audio as $audio): ?>
                            <div class="recent-item">
                                <div class="recent-icon">
                                    <i class="fas fa-music"></i>
                                </div>
                                <div class="recent-content">
                                    <div class="recent-title"><?php echo htmlspecialchars($audio['title'] ?? 'Untitled'); ?></div>
                                    <div class="recent-meta">
                                        <span><i class="far fa-calendar"></i> <?php echo isset($audio['created_at']) ? formatDate($audio['created_at']) : 'Unknown'; ?></span>
                                        <span><i class="far fa-play-circle"></i> <?php echo number_format($audio['plays'] ?? 0); ?> plays</span>
                                    </div>
                                </div>
                                <a href="edit-audio.php?id=<?php echo $audio['id'] ?? 0; ?>" class="recent-edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3>Quick Actions</h3>
                <div class="actions-grid">
                    <a href="add-post.php" class="action-btn">
                        <i class="fas fa-pen"></i>
                        <span>New Post</span>
                    </a>
                    <a href="add-audio.php" class="action-btn">
                        <i class="fas fa-microphone"></i>
                        <span>Upload Audio</span>
                    </a>
                    <a href="media.php" class="action-btn">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Upload Media</span>
                    </a>
                    <a href="site-settings.php" class="action-btn">
                        <i class="fas fa-palette"></i>
                        <span>Customize</span>
                    </a>
                    <a href="?action=clear_cache" class="action-btn" onclick="return confirm('Clear system cache?')">
                        <i class="fas fa-trash-alt"></i>
                        <span>Clear Cache</span>
                    </a>
                    <a href="?action=backup_settings" class="action-btn" onclick="return confirm('Create settings backup?')">
                        <i class="fas fa-download"></i>
                        <span>Backup</span>
                    </a>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="system-info">
                <h3>System Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">PHP Version</div>
                        <div class="info-value"><?php echo htmlspecialchars($php_version); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">MySQL Version</div>
                        <div class="info-value"><?php echo htmlspecialchars($mysql_version); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Server Software</div>
                        <div class="info-value"><?php echo htmlspecialchars($server_software); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Upload Max Size</div>
                        <div class="info-value"><?php echo htmlspecialchars($upload_max_filesize); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Post Max Size</div>
                        <div class="info-value"><?php echo htmlspecialchars($post_max_size); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Memory Limit</div>
                        <div class="info-value"><?php echo htmlspecialchars($memory_limit); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Server Time</div>
                        <div class="info-value"><?php echo htmlspecialchars($current_time); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Timezone</div>
                        <div class="info-value"><?php echo htmlspecialchars($timezone); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts JavaScript -->
    <script>
        // Mobile Menu Toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const adminSidebar = document.getElementById('adminSidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            adminSidebar.classList.toggle('sidebar-open');
            sidebarOverlay.classList.toggle('active');
            
            // Change icon
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
        
        // Close sidebar when clicking a link (on mobile)
        const sidebarLinks = document.querySelectorAll('.sidebar-menu-item');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function(e) {
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
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            const viewsCanvas = document.getElementById('viewsChart');
            const distCanvas = document.getElementById('distributionChart');
            
            if (viewsCanvas) {
                const viewsCtx = viewsCanvas.getContext('2d');
                new Chart(viewsCtx, {
                    type: 'line',
                    data: {
                        labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                        datasets: [{
                            label: 'Blog Views',
                            data: [65, 59, 80, 81],
                            borderColor: '#4a7c59',
                            backgroundColor: 'rgba(74, 124, 89, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#4a7c59'
                        }, {
                            label: 'Audio Plays',
                            data: [28, 48, 40, 59],
                            borderColor: '#2c4a3b',
                            backgroundColor: 'rgba(44, 74, 59, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#2c4a3b'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 15,
                                    font: { size: 11 }
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { drawBorder: false },
                                ticks: { font: { size: 11 } }
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 11 } }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
            }
            
            if (distCanvas) {
                const distCtx = distCanvas.getContext('2d');
                new Chart(distCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Blog Posts', 'Audio Messages'],
                        datasets: [{
                            data: [
                                <?php echo max(1, $stats['total_posts']); ?>,
                                <?php echo max(1, $stats['total_audio']); ?>
                            ],
                            backgroundColor: ['#4a7c59', '#2c4a3b'],
                            borderWidth: 0,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    padding: 15,
                                    font: { size: 11 }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = Math.round((value / total) * 100);
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        },
                        cutout: '65%'
                    }
                });
            }
        });
        
        // Period change handler
        document.getElementById('viewsPeriod')?.addEventListener('change', function() {
            console.log('Period changed to:', this.value);
        });
        
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
        
        // Touch-friendly: Remove hover effects on mobile
        if ('ontouchstart' in window) {
            document.querySelectorAll('.stat-card, .action-btn, .sidebar-menu-item').forEach(el => {
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