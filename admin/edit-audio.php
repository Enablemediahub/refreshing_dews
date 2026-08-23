<?php
/**
 * Edit Audio Message - Admin Panel
 * Edit existing audio messages with file uploads
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

// Get audio ID from URL
$audio_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($audio_id <= 0) {
    header('Location: audio.php');
    exit();
}

// Get audio details
$stmt = $conn->prepare("SELECT * FROM audio_messages WHERE id = ?");
$stmt->bind_param("i", $audio_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: audio.php');
    exit();
}

$audio = $result->fetch_assoc();

// Initialize variables
$success_message = '';
$error_message = '';
$title = $audio['title'];
$description = $audio['description'];
$duration = $audio['duration'];
$status = $audio['status'];
$current_audio_file = $audio['audio_file'];
$current_cover_image = $audio['cover_image'];

// Define upload directories
$audio_upload_dir = '../uploads/audio/';
$image_upload_dir = '../uploads/images/';

// Create directories if they don't exist
if (!file_exists($audio_upload_dir)) {
    mkdir($audio_upload_dir, 0777, true);
}
if (!file_exists($image_upload_dir)) {
    mkdir($image_upload_dir, 0777, true);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is the delete action
    if (isset($_POST['delete_audio'])) {
        // Delete audio file
        if (!empty($audio['audio_file']) && file_exists('../' . $audio['audio_file'])) {
            @unlink('../' . $audio['audio_file']);
        }
        
        // Delete cover image
        if (!empty($audio['cover_image']) && file_exists($image_upload_dir . $audio['cover_image'])) {
            @unlink($image_upload_dir . $audio['cover_image']);
        }
        
        // Delete from database
        $stmt = $conn->prepare("DELETE FROM audio_messages WHERE id = ?");
        $stmt->bind_param("i", $audio_id);
        
        if ($stmt->execute()) {
            if (function_exists('logAdminAction')) {
                logAdminAction('delete_audio', 'Deleted audio: ' . $audio['title']);
            }
            header('Location: audio.php?msg=' . urlencode('Audio message deleted successfully'));
            exit();
        } else {
            $error_message = 'Failed to delete audio message.';
        }
        $stmt->close();
    } 
    // This is the update action
    else {
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
        $audio_file = $current_audio_file;
        
        if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadAudioFile($_FILES['audio_file'], $audio_upload_dir);
            if ($upload_result['success']) {
                // Delete old audio file
                if (!empty($current_audio_file) && file_exists('../' . $current_audio_file)) {
                    @unlink('../' . $current_audio_file);
                }
                $audio_file = $upload_result['filepath'];
                // Auto-calculate duration if not provided
                if (empty($duration) && !empty($upload_result['duration'])) {
                    $duration = $upload_result['duration'];
                }
                $success_message = 'Audio file uploaded successfully! ';
            } else {
                $errors[] = $upload_result['error'];
            }
        }
        
        // Handle cover image upload
        $cover_image = $current_cover_image;
        
        if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
            $upload_result = uploadCoverImage($_FILES['cover_image'], $image_upload_dir);
            if ($upload_result['success']) {
                // Delete old cover image
                if (!empty($current_cover_image) && file_exists($image_upload_dir . $current_cover_image)) {
                    @unlink($image_upload_dir . $current_cover_image);
                }
                $cover_image = $upload_result['filename'];
                if (empty($success_message)) {
                    $success_message = 'Cover image uploaded successfully! ';
                } else {
                    $success_message .= 'Cover image uploaded successfully!';
                }
            } else {
                $errors[] = $upload_result['error'];
            }
        }
        
        // Check if remove cover image is checked
        if (isset($_POST['remove_cover_image']) && $_POST['remove_cover_image'] == '1') {
            if (!empty($current_cover_image) && file_exists($image_upload_dir . $current_cover_image)) {
                @unlink($image_upload_dir . $current_cover_image);
            }
            $cover_image = '';
            if (empty($success_message)) {
                $success_message = 'Cover image removed successfully! ';
            } else {
                $success_message .= ' Cover image removed.';
            }
        }
        
        // If no errors, update database (removed updated_at column)
        if (empty($errors)) {
            $stmt = $conn->prepare("
                UPDATE audio_messages 
                SET title = ?, description = ?, audio_file = ?, duration = ?, cover_image = ?, status = ?
                WHERE id = ?
            ");
            
            $stmt->bind_param("ssssssi", $title, $description, $audio_file, $duration, $cover_image, $status, $audio_id);
            
            if ($stmt->execute()) {
                if (empty($success_message)) {
                    $success_message = 'Audio message updated successfully!';
                } else {
                    $success_message .= ' Audio message updated successfully!';
                }
                
                // Log the action
                if (function_exists('logAdminAction')) {
                    logAdminAction('edit_audio', 'Updated audio: ' . $title);
                }
                
                // Refresh audio data for display
                $current_audio_file = $audio_file;
                $current_cover_image = $cover_image;
            } else {
                $error_message = 'Database error: ' . $conn->error;
            }
            $stmt->close();
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}

// Function to upload audio file
function uploadAudioFile($file, $target_dir) {
    $allowed_extensions = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'mp4'];
    
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file extension
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Invalid audio format. Allowed: MP3, WAV, OGG, AAC, M4A'];
    }
    
    // Check file size (max 50MB)
    if ($file_size > 50 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Audio file too large. Max size: 50MB'];
    }
    
    // Generate unique filename
    $unique_filename = 'audio_' . uniqid() . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $unique_filename;
    $db_path = 'uploads/audio/' . $unique_filename;
    
    // Upload file
    if (move_uploaded_file($file_tmp, $target_file)) {
        return [
            'success' => true, 
            'filepath' => $db_path,
            'duration' => ''
        ];
    } else {
        return ['success' => false, 'error' => 'Failed to upload audio file. Check directory permissions.'];
    }
}

// Function to upload cover image
function uploadCoverImage($file, $target_dir) {
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
        return ['success' => false, 'error' => 'Failed to upload cover image.'];
    }
}

// Get site settings
$site_title = getSetting('site_title', 'Painlesslyf');
$site_logo = getSetting('site_logo', 'assets/logo/painlesslyf-logo.png');
$primary_color = getSetting('primary_color', '#4a7c59');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Edit Audio Message - Admin Panel</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; color: #333; }
        
        .admin-wrapper { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .admin-sidebar {
            width: 280px;
            background: linear-gradient(135deg, #2c3e50 0%, #1e2b37 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
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
        
        .sidebar-header h3 { font-size: 18px; font-weight: 600; }
        .sidebar-header p { font-size: 14px; color: rgba(255,255,255,0.6); margin-top: 5px; }
        
        .sidebar-menu { padding: 20px 0; }
        
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
        
        .sidebar-menu-item i { width: 20px; font-size: 18px; }
        .sidebar-menu-item:hover, .sidebar-menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: <?php echo $primary_color; ?>;
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
            margin-left: 280px;
            padding: 30px;
        }
        
        @media (max-width: 1024px) {
            .admin-sidebar { width: 80px; }
            .sidebar-header h3, .sidebar-header p, .sidebar-menu-item span, .sidebar-menu-label { display: none; }
            .sidebar-header img { max-width: 50px; padding: 5px; }
            .admin-main { margin-left: 80px; }
        }
        
        @media (max-width: 768px) {
            .admin-main { margin-left: 0; padding: 15px; }
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
        
        .top-nav-title h1 { font-size: 24px; font-weight: 600; color: #333; }
        .top-nav-title p { color: #666; font-size: 14px; margin-top: 5px; }
        
        .top-nav-user { display: flex; align-items: center; gap: 20px; }
        .user-info { text-align: right; }
        .user-name { font-weight: 600; color: #333; }
        .user-role { font-size: 12px; color: #666; }
        
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
        
        .form-container { max-width: 800px; margin: 0 auto; }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; font-size: 14px; }
        
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
        
        textarea.form-control { min-height: 120px; resize: vertical; }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        /* Upload Container */
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
        
        .upload-container h4 { font-size: 18px; color: #333; margin-bottom: 10px; }
        .upload-container p { color: #666; margin-bottom: 15px; }
        .upload-container small { color: #999; font-size: 12px; }
        
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
        
        .file-preview.show { display: flex; }
        
        .file-info { flex: 1; }
        .file-name { font-weight: 600; color: #333; margin-bottom: 3px; }
        .file-meta { font-size: 12px; color: #999; }
        
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
        
        .current-file {
            margin-top: 10px;
            padding: 12px;
            background: #e8f5e9;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .current-file i { font-size: 20px; color: <?php echo $primary_color; ?>; }
        .current-file span { font-size: 13px; color: #333; }
        
        .image-preview-container {
            margin-top: 15px;
            display: none;
            position: relative;
            width: fit-content;
        }
        
        .image-preview-container.show { display: block; }
        
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
        
        .current-image {
            margin-top: 10px;
            padding: 12px;
            background: #e8f5e9;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .current-image img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .current-image .image-info { flex: 1; }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            margin-top: 5px;
        }
        
        .duration-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .duration-hint { font-size: 13px; color: #999; }
        
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
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
            flex-wrap: wrap;
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
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
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
        
        @media (max-width: 768px) {
            .form-actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
            .top-nav { flex-direction: column; text-align: center; }
            .top-nav-user { width: 100%; justify-content: center; }
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
                    <h1>Edit Audio Message</h1>
                    <p>Edit audio message details</p>
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
                    <!-- Success Message -->
                    <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div><?php echo htmlspecialchars($success_message); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Error Message -->
                    <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div><?php echo $error_message; ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Edit Form -->
                    <form method="POST" enctype="multipart/form-data">
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
                                      placeholder="Describe this audio message"><?php echo htmlspecialchars($description); ?></textarea>
                        </div>
                        
                        <!-- Audio File Upload -->
                        <div class="form-group">
                            <label>Audio File</label>
                            <div class="upload-container" id="audioUploadContainer">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h4>Click or drag audio file to replace</h4>
                                <p>Supported formats: MP3, WAV, OGG, AAC, M4A (Max: 50MB)</p>
                                <small>Files stored in: uploads/audio/</small>
                                <input type="file" name="audio_file" id="audioFile" accept=".mp3,.wav,.ogg,.aac,.m4a,.mp4">
                            </div>
                            
                            <?php if (!empty($current_audio_file)): ?>
                            <div class="current-file">
                                <i class="fas fa-music"></i>
                                <span>Current: <?php echo basename($current_audio_file); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="file-preview" id="audioPreview">
                                <div class="file-icon"><i class="fas fa-music"></i></div>
                                <div class="file-info">
                                    <div class="file-name" id="audioFileName">No file selected</div>
                                    <div class="file-meta" id="audioFileMeta"></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Duration and Status -->
                        <div class="form-row">
                            <div class="form-group">
                                <label for="duration">Duration (MM:SS)</label>
                                <div class="duration-input-group">
                                    <input type="text" class="form-control" id="duration" name="duration" 
                                           value="<?php echo htmlspecialchars($duration); ?>" 
                                           placeholder="e.g., 3:45">
                                    <span class="duration-hint">Auto-detected for new files</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status">
                                    <option value="draft" <?php echo $status == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo $status == 'published' ? 'selected' : ''; ?>>Published</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Cover Image Upload -->
                        <div class="form-group">
                            <label>Cover Image (Optional)</label>
                            <div class="upload-container" id="imageUploadContainer">
                                <i class="fas fa-image"></i>
                                <h4>Click or drag cover image to replace</h4>
                                <p>Recommended: 500x500 pixels (Max: 5MB)</p>
                                <small>Formats: JPG, PNG, GIF, WEBP. Stored in: uploads/images/</small>
                                <input type="file" name="cover_image" id="coverImage" accept=".jpg,.jpeg,.png,.gif,.webp">
                            </div>
                            
                            <?php if (!empty($current_cover_image)): ?>
                            <div class="current-image">
                                <img src="../uploads/images/<?php echo $current_cover_image; ?>" alt="Current cover">
                                <div class="image-info">
                                    <p>Current: <?php echo $current_cover_image; ?></p>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="remove_cover_image" value="1">
                                        <span>Remove this image</span>
                                    </label>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="image-preview-container" id="imagePreviewContainer">
                                <img src="" alt="Preview" class="image-preview" id="imagePreview">
                                <button type="button" class="remove-image" id="removeImage"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" name="update_audio" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Audio Message
                            </button>
                            <button type="submit" name="delete_audio" class="btn btn-danger" 
                                    onclick="return confirm('Delete this audio message permanently?')">
                                <i class="fas fa-trash"></i> Delete
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
        function formatFileSize(bytes) {
            if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(2) + ' GB';
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return bytes + ' bytes';
        }
        
        // Audio file handling
        const audioFile = document.getElementById('audioFile');
        const audioPreview = document.getElementById('audioPreview');
        const audioFileName = document.getElementById('audioFileName');
        const audioFileMeta = document.getElementById('audioFileMeta');
        
        audioFile.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const validExt = ['mp3', 'wav', 'ogg', 'aac', 'm4a', 'mp4'];
                const ext = file.name.split('.').pop().toLowerCase();
                
                if (!validExt.includes(ext)) {
                    alert('Invalid format. Allowed: MP3, WAV, OGG, AAC, M4A');
                    this.value = '';
                    audioPreview.classList.remove('show');
                    return;
                }
                
                if (file.size > 50 * 1024 * 1024) {
                    alert('File too large. Max: 50MB');
                    this.value = '';
                    audioPreview.classList.remove('show');
                    return;
                }
                
                audioFileName.textContent = file.name;
                audioFileMeta.textContent = formatFileSize(file.size) + ' • ' + ext.toUpperCase();
                audioPreview.classList.add('show');
                
                const audio = new Audio(URL.createObjectURL(file));
                audio.addEventListener('loadedmetadata', function() {
                    const minutes = Math.floor(audio.duration / 60);
                    const seconds = Math.floor(audio.duration % 60);
                    const durationStr = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                    if (!document.getElementById('duration').value) {
                        document.getElementById('duration').value = durationStr;
                    }
                });
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
                const validExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                const ext = file.name.split('.').pop().toLowerCase();
                
                if (!validExt.includes(ext)) {
                    alert('Invalid format. Allowed: JPG, PNG, GIF, WEBP');
                    this.value = '';
                    return;
                }
                
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image too large. Max: 5MB');
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
        
        // Drag and drop
        const audioContainer = document.getElementById('audioUploadContainer');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            audioContainer.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            audioContainer.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            audioContainer.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            audioContainer.style.background = '#e8f5e9';
            audioContainer.style.borderColor = '#2c4a3b';
        }
        
        function unhighlight() {
            audioContainer.style.background = '#f8fff8';
            audioContainer.style.borderColor = '<?php echo $primary_color; ?>';
        }
        
        audioContainer.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const files = e.dataTransfer.files;
            audioFile.files = files;
            const event = new Event('change', { bubbles: true });
            audioFile.dispatchEvent(event);
        }
        
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
        
        // Duration formatting
        document.getElementById('duration').addEventListener('input', function(e) {
            let value = this.value.replace(/[^\d:]/g, '');
            if (value.length > 5) value = value.slice(0, 5);
            this.value = value;
        });
    </script>
</body>
</html>