<?php
/**
 * Add Audio Message - Admin Panel
 * Upload audio files to uploads/audio/ and cover images to uploads/images/
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
$title = '';
$description = '';
$duration = '';

// Define upload directories (absolute paths from admin folder)
$audio_upload_dir = '../uploads/audio/';
$image_upload_dir = '../uploads/images/';

// Create directories if they don't exist
if (!file_exists($audio_upload_dir)) {
    mkdir($audio_upload_dir, 0777, true);
}
if (!file_exists($image_upload_dir)) {
    mkdir($image_upload_dir, 0777, true);
}

// Verify directories are writable
if (!is_writable($audio_upload_dir)) {
    chmod($audio_upload_dir, 0777);
}
if (!is_writable($image_upload_dir)) {
    chmod($image_upload_dir, 0777);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    
    // Validate required fields
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Title is required.';
    }
    
    // Handle audio file upload
    $audio_file = '';
    if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadAudioFile($_FILES['audio_file'], $audio_upload_dir);
        if ($upload_result['success']) {
            $audio_file = $upload_result['filepath'];
            // Auto-calculate duration if not provided
            if (empty($duration) && !empty($upload_result['duration'])) {
                $duration = $upload_result['duration'];
            }
        } else {
            $errors[] = $upload_result['error'];
        }
    } else {
        // Check if there was an upload error
        if (isset($_FILES['audio_file'])) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_FILE => 'No audio file was selected.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
            ];
            $error_code = $_FILES['audio_file']['error'];
            $errors[] = $upload_errors[$error_code] ?? 'Audio file upload failed.';
        } else {
            $errors[] = 'Audio file is required.';
        }
    }
    
    // Handle cover image upload (optional)
    $cover_image = '';
    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_result = uploadCoverImage($_FILES['cover_image'], $image_upload_dir);
        if ($upload_result['success']) {
            $cover_image = $upload_result['filename'];
        } else {
            $errors[] = $upload_result['error'];
        }
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO audio_messages (
                title, description, audio_file, duration, cover_image, author_id, status, plays, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $stmt->bind_param("sssssis", $title, $description, $audio_file, $duration, $cover_image, $admin_id, $status);
        
        if ($stmt->execute()) {
            $audio_id = $stmt->insert_id;
            $success_message = 'Audio message added successfully!';
            
            // Log the action
            if (function_exists('logAdminAction')) {
                logAdminAction('add_audio', 'Added new audio: ' . $title . ' (ID: ' . $audio_id . ')');
            }
            
            // Clear form
            $title = '';
            $description = '';
            $duration = '';
            
            // Redirect to edit page after 2 seconds
            header("refresh:2;url=edit-audio.php?id=$audio_id");
        } else {
            $error_message = 'Database error: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error_message = implode('<br>', $errors);
    }
}

// Function to upload audio file
function uploadAudioFile($file, $target_dir) {
    // Allowed audio extensions
    $allowed_extensions = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'mp4'];
    
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $original_name = $file['name'];
    
    // Validate file extension
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid audio format. Allowed: MP3, WAV, OGG, AAC, M4A'];
    }
    
    // Check file size (max 50MB)
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file_size > $max_size) {
        return ['success' => false, 'error' => 'Audio file too large. Max size: 50MB'];
    }
    
    // Generate unique filename
    $unique_filename = 'audio_' . uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $unique_filename;
    $db_path = 'uploads/audio/' . $unique_filename;
    
    // Upload file
    if (move_uploaded_file($file_tmp, $target_file)) {
        // Try to detect duration using JavaScript (client-side) - will be set via JS
        $duration = '';
        
        return [
            'success' => true, 
            'filepath' => $db_path,
            'filename' => $unique_filename,
            'duration' => $duration,
            'original_name' => $original_name
        ];
    } else {
        $error = error_get_last();
        return ['success' => false, 'error' => 'Failed to upload audio file. Check directory permissions: ' . ($error['message'] ?? '')];
    }
}

// Function to upload cover image
function uploadCoverImage($file, $target_dir) {
    // Allowed image extensions
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file extension
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid image format. Allowed: JPG, PNG, GIF, WEBP'];
    }
    
    // Check file size (max 5MB)
    if ($file_size > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Image too large. Max size: 5MB'];
    }
    
    // Generate unique filename
    $unique_filename = 'cover_' . uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $unique_filename;
    
    // Upload file
    if (move_uploaded_file($file_tmp, $target_file)) {
        return ['success' => true, 'filename' => $unique_filename];
    } else {
        $error = error_get_last();
        return ['success' => false, 'error' => 'Failed to upload cover image. Check directory permissions: ' . ($error['message'] ?? '')];
    }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Add Audio Message - Admin Panel</title>
    
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
            border-left-color: <?php echo $primary_color; ?>;
        }
        
        .sidebar-menu-label {
            padding: 10px 25px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
        }
        
        .sidebar-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 15px 20px;
        }
        
        /* Main Content Area */
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: #f4f6f9;
            min-height: 100vh;
        }
        
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
            background: linear-gradient(135deg, <?php echo $primary_color; ?>, #2c4a3b);
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
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        /* Form Styles */
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: <?php echo $primary_color; ?>;
            box-shadow: 0 0 0 3px rgba(74, 124, 89, 0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        /* File Upload */
        .upload-container {
            border: 2px dashed <?php echo $primary_color; ?>;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            position: relative;
            transition: all 0.3s;
            background: #f8fff8;
            cursor: pointer;
            margin-bottom: 10px;
        }
        
        .upload-container:hover {
            background: #f0f8f0;
            border-color: #2c4a3b;
        }
        
        .upload-container i {
            font-size: 48px;
            color: <?php echo $primary_color; ?>;
            margin-bottom: 15px;
        }
        
        .upload-container h4 {
            font-size: 18px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .upload-container p {
            color: #666;
            margin-bottom: 15px;
        }
        
        .upload-container small {
            color: #999;
            font-size: 12px;
        }
        
        .upload-container input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-preview {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            display: none;
            align-items: center;
            gap: 15px;
        }
        
        .file-preview.show {
            display: flex;
        }
        
        .file-info {
            flex: 1;
        }
        
        .file-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
        }
        
        .file-meta {
            font-size: 12px;
            color: #999;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            background: <?php echo $primary_color; ?>;
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        /* Image Preview */
        .image-preview-container {
            margin-top: 15px;
            display: none;
            position: relative;
            width: fit-content;
        }
        
        .image-preview-container.show {
            display: block;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            border: 3px solid <?php echo $primary_color; ?>;
        }
        
        .remove-image {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 30px;
            height: 30px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .remove-image:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        
        /* Duration Input Group */
        .duration-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .duration-hint {
            font-size: 13px;
            color: #999;
        }
        
        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
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
        
        /* Buttons */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: <?php echo $primary_color; ?>;
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
        
        /* Progress Bar */
        .upload-progress {
            margin-top: 15px;
            display: none;
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: <?php echo $primary_color; ?>;
            width: 0%;
            transition: width 0.3s;
        }
        
        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .top-nav {
                flex-direction: column;
                text-align: center;
            }
            
            .top-nav-user {
                width: 100%;
                justify-content: center;
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
                    <h1>Add Audio Message</h1>
                    <p>Upload audio files to <strong>uploads/audio/</strong> and cover images to <strong>uploads/images/</strong></p>
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
                <div class="form-container">
                    <!-- Messages -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <?php echo htmlspecialchars($success_message); ?>
                            <br><small>Redirecting to edit page...</small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <?php echo $error_message; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Add Form -->
                    <form method="POST" enctype="multipart/form-data" id="audioForm">
                        <!-- Title -->
                        <div class="form-group">
                            <label for="title">Audio Title <span style="color: #dc3545;">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($title); ?>" 
                                   placeholder="Enter a descriptive title" required>
                        </div>
                        
                        <!-- Description -->
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" 
                                      placeholder="Describe this audio message (optional)"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                        
                        <!-- Audio File Upload -->
                        <div class="form-group">
                            <label>Audio File <span style="color: #dc3545;">*</span></label>
                            <div class="upload-container" id="audioUploadContainer">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h4>Click or drag audio file to upload</h4>
                                <p>Supported formats: MP3, WAV, OGG, AAC, M4A (Max: 50MB)</p>
                                <small>Files will be stored in: <strong>uploads/audio/</strong></small>
                                <input type="file" name="audio_file" id="audioFile" accept=".mp3,.wav,.ogg,.aac,.m4a,.mp4" required>
                            </div>
                            
                            <!-- Audio File Preview -->
                            <div class="file-preview" id="audioPreview">
                                <div class="file-icon">
                                    <i class="fas fa-music"></i>
                                </div>
                                <div class="file-info">
                                    <div class="file-name" id="audioFileName">No file selected</div>
                                    <div class="file-meta" id="audioFileMeta"></div>
                                </div>
                            </div>
                            
                            <!-- Upload Progress -->
                            <div class="upload-progress" id="uploadProgress">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progressFill"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Duration and Status Row -->
                        <div class="form-row">
                            <!-- Duration -->
                            <div class="form-group">
                                <label for="duration">Duration (MM:SS)</label>
                                <div class="duration-input-group">
                                    <input type="text" class="form-control" id="duration" name="duration" 
                                           value="<?php echo htmlspecialchars($duration); ?>" 
                                           placeholder="e.g., 3:45">
                                    <span class="duration-hint">Auto-detected if possible</span>
                                </div>
                            </div>
                            
                            <!-- Status -->
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="draft" selected>Draft</option>
                                    <option value="published">Published</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Cover Image Upload -->
                        <div class="form-group">
                            <label>Cover Image (Optional)</label>
                            <div class="upload-container" id="imageUploadContainer">
                                <i class="fas fa-image"></i>
                                <h4>Click or drag cover image</h4>
                                <p>Recommended size: 500x500 pixels (Max: 5MB)</p>
                                <small>Formats: JPG, PNG, GIF, WEBP. Stored in: <strong>uploads/images/</strong></small>
                                <input type="file" name="cover_image" id="coverImage" accept=".jpg,.jpeg,.png,.gif,.webp">
                            </div>
                            
                            <!-- Image Preview -->
                            <div class="image-preview-container" id="imagePreviewContainer">
                                <img src="" alt="Cover Preview" class="image-preview" id="imagePreview">
                                <button type="button" class="remove-image" id="removeImage" title="Remove image">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Add Audio Message
                            </button>
                            
                            <a href="audio.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Client-side file size formatter
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) {
                return (bytes / 1073741824).toFixed(2) + ' GB';
            } else if (bytes >= 1048576) {
                return (bytes / 1048576).toFixed(2) + ' MB';
            } else if (bytes >= 1024) {
                return (bytes / 1024).toFixed(2) + ' KB';
            } else {
                return bytes + ' bytes';
            }
        }
        
        // Audio file upload handling
        const audioFile = document.getElementById('audioFile');
        const audioPreview = document.getElementById('audioPreview');
        const audioFileName = document.getElementById('audioFileName');
        const audioFileMeta = document.getElementById('audioFileMeta');
        const uploadProgress = document.getElementById('uploadProgress');
        const progressFill = document.getElementById('progressFill');
        
        audioFile.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file extension
                const validExtensions = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'mp4'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                
                if (!validExtensions.includes(fileExtension)) {
                    alert('Invalid audio format. Allowed: MP3, WAV, OGG, AAC, M4A');
                    this.value = '';
                    audioPreview.classList.remove('show');
                    return;
                }
                
                // Check file size (50MB)
                if (file.size > 50 * 1024 * 1024) {
                    alert('File too large. Max size: 50MB');
                    this.value = '';
                    audioPreview.classList.remove('show');
                    return;
                }
                
                // Show preview
                audioFileName.textContent = file.name;
                const size = formatFileSize(file.size);
                audioFileMeta.textContent = `${size} • ${fileExtension.toUpperCase()}`;
                audioPreview.classList.add('show');
                
                // Try to auto-detect duration
                const audio = new Audio(URL.createObjectURL(file));
                audio.addEventListener('loadedmetadata', function() {
                    const minutes = Math.floor(audio.duration / 60);
                    const seconds = Math.floor(audio.duration % 60);
                    const durationStr = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    
                    // Only set if not manually entered
                    if (!document.getElementById('duration').value) {
                        document.getElementById('duration').value = durationStr;
                    }
                });
                
                // Show upload progress animation
                uploadProgress.style.display = 'block';
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 10;
                    progressFill.style.width = progress + '%';
                    if (progress >= 100) {
                        clearInterval(interval);
                        setTimeout(() => {
                            uploadProgress.style.display = 'none';
                            progressFill.style.width = '0%';
                        }, 500);
                    }
                }, 100);
            } else {
                audioPreview.classList.remove('show');
            }
        });
        
        // Cover image handling
        const coverImage = document.getElementById('coverImage');
        const imagePreview = document.getElementById('imagePreview');
        const imagePreviewContainer = document.getElementById('imagePreviewContainer');
        const removeImageBtn = document.getElementById('removeImage');
        
        coverImage.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file extension
                const validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const fileExtension = file.name.split('.').pop().toLowerCase();
                
                if (!validExtensions.includes(fileExtension)) {
                    alert('Invalid image format. Allowed: JPG, PNG, GIF, WEBP');
                    this.value = '';
                    return;
                }
                
                // Check file size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image too large. Max size: 5MB');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    imagePreviewContainer.classList.add('show');
                };
                reader.readAsDataURL(file);
            }
        });
        
        removeImageBtn.addEventListener('click', function() {
            coverImage.value = '';
            imagePreviewContainer.classList.remove('show');
        });
        
        // Drag and drop for audio
        const audioUploadContainer = document.getElementById('audioUploadContainer');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            audioUploadContainer.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            audioUploadContainer.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            audioUploadContainer.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            audioUploadContainer.style.background = '#e8f5e9';
            audioUploadContainer.style.borderColor = '#2c4a3b';
        }
        
        function unhighlight() {
            audioUploadContainer.style.background = '#f8fff8';
            audioUploadContainer.style.borderColor = '<?php echo $primary_color; ?>';
        }
        
        audioUploadContainer.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            audioFile.files = files;
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            audioFile.dispatchEvent(event);
        }
        
        // Form validation
        document.getElementById('audioForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        });
        
        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
            }, 5000);
        });
        
        // Duration input formatting
        document.getElementById('duration').addEventListener('input', function(e) {
            let value = this.value.replace(/[^\d:]/g, '');
            if (value.length > 5) {
                value = value.slice(0, 5);
            }
            this.value = value;
        });
        
        // Confirm before leaving if form is dirty
        let formDirty = false;
        const form = document.getElementById('audioForm');
        const formInputs = form.querySelectorAll('input, textarea, select');
        
        formInputs.forEach(input => {
            input.addEventListener('change', () => {
                formDirty = true;
            });
            input.addEventListener('keyup', () => {
                formDirty = true;
            });
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formDirty && !document.getElementById('submitBtn').disabled) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        form.addEventListener('submit', function() {
            formDirty = false;
        });
    </script>
</body>
</html>