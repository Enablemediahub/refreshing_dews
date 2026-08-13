<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$post = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'content' => '',
    'excerpt' => '',
    'featured_image' => '',
    'status' => 'draft',
    'category' => 'Life'
];

// Categories
$categories = ['Life', 'Thoughts', 'Experiences', 'Inspiration', 'Growth', 'Reflections'];

// Check if editing existing post
$is_edit = isset($_GET['id']) && is_numeric($_GET['id']);
if ($is_edit) {
    $post_id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $post = $result->fetch_assoc();
    } else {
        header('Location: posts.php');
        exit();
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_post'])) {
    $title = trim($_POST['title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $excerpt = trim($_POST['excerpt'] ?? '');
    $status = $_POST['status'] ?? 'draft';
    $category = $_POST['category'] ?? 'Life';
    
    // Log the submission for debugging
    error_log("=== POST SUBMISSION ===");
    error_log("Title: " . $title);
    error_log("Content length: " . strlen($content));
    error_log("Category: " . $category);
    error_log("Status: " . $status);
    error_log("Is Edit: " . ($is_edit ? 'Yes' : 'No'));
    
    // Validate required fields
    if (empty($title)) {
        $error_message = 'Please enter a post title.';
        error_log("Error: Title is empty");
    } elseif (empty($content)) {
        $error_message = 'Please enter post content.';
        error_log("Error: Content is empty");
    } elseif (empty($category)) {
        $error_message = 'Please select a category.';
        error_log("Error: Category is empty");
    } else {
        // Generate slug if empty
        if (empty($slug)) {
            $slug = createSlug($title);
            error_log("Generated slug: " . $slug);
        }
        
        // Check if slug is unique
        if ($is_edit) {
            $check_sql = "SELECT id FROM posts WHERE slug = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $slug, $post_id);
        } else {
            $check_sql = "SELECT id FROM posts WHERE slug = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $slug);
        }
        
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows > 0) {
            $slug = $slug . '-' . time();
            error_log("Slug not unique, new slug: " . $slug);
        }
        $check_stmt->close();
        
        // Handle featured image upload
        $featured_image = $post['featured_image'];
        
        // Check if image should be removed
        if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
            if (!empty($post['featured_image'])) {
                $old_image_path = '../uploads/images/' . $post['featured_image'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                    error_log("Removed old image: " . $post['featured_image']);
                }
            }
            $featured_image = '';
        }
        
        // Handle new image upload
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/images/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
                error_log("Created upload directory");
            }
            
            $file = $_FILES['featured_image'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($extension, $allowed)) {
                $new_filename = 'featured_' . time() . '_' . uniqid() . '.' . $extension;
                $target = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    // Delete old image if exists
                    if ($is_edit && !empty($post['featured_image'])) {
                        $old_image = $upload_dir . $post['featured_image'];
                        if (file_exists($old_image)) {
                            unlink($old_image);
                        }
                    }
                    $featured_image = $new_filename;
                    error_log("Uploaded new image: " . $new_filename);
                } else {
                    $error_message = 'Failed to upload image. Check folder permissions.';
                    error_log("Failed to move uploaded file");
                }
            } else {
                $error_message = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP';
                error_log("Invalid file type: " . $extension);
            }
        }
        
        // Generate excerpt if empty
        if (empty($error_message) && empty($excerpt)) {
            $excerpt = substr(strip_tags($content), 0, 150) . '...';
            error_log("Generated excerpt");
        }
        
        // Save to database
        if (empty($error_message)) {
            if ($is_edit) {
                $sql = "UPDATE posts SET title = ?, slug = ?, content = ?, excerpt = ?, featured_image = ?, status = ?, category = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssi", $title, $slug, $content, $excerpt, $featured_image, $status, $category, $post_id);
                error_log("Executing UPDATE for ID: " . $post_id);
            } else {
                $sql = "INSERT INTO posts (title, slug, content, excerpt, featured_image, author_id, status, category, views, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssiss", $title, $slug, $content, $excerpt, $featured_image, $admin_id, $status, $category);
                error_log("Executing INSERT with admin_id: " . $admin_id);
            }
            
            if ($stmt->execute()) {
                if ($is_edit) {
                    $success_message = 'Post updated successfully!';
                    error_log("Post updated successfully");
                    
                    // Refresh post data
                    $refresh = $conn->prepare("SELECT * FROM posts WHERE id = ?");
                    $refresh->bind_param("i", $post_id);
                    $refresh->execute();
                    $result = $refresh->get_result();
                    $post = $result->fetch_assoc();
                    $refresh->close();
                } else {
                    $new_id = $conn->insert_id;
                    $success_message = 'Post created successfully!';
                    error_log("New post created with ID: " . $new_id);
                    
                    // Redirect to edit page
                    header('Location: add-post.php?id=' . $new_id . '&success=1');
                    exit();
                }
            } else {
                $error_message = 'Database error: ' . $stmt->error;
                error_log("Execute failed: " . $stmt->error);
            }
            $stmt->close();
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = 'Post created successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Post' : 'Add New Post'; ?> - Admin Panel</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin-theme.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Summernote CSS & JS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        /* Your existing CSS here - keeping it minimal for now */
        :root {
            --primary: #4a7c59;
            --primary-dark: #2c4a3b;
            --dark: #1a2a1f;
            --light: #f9fbf9;
            --border: #e9ecef;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
        }
        
        .admin-wrapper { display: flex; min-height: 100vh; }
        
        .admin-sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-size: 18px; font-weight: 600; }
        .sidebar-header p { font-size: 14px; opacity: 0.8; }
        
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
        .sidebar-menu-item:hover, .sidebar-menu-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        .sidebar-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 15px 20px; }
        
        .admin-main { flex: 1; margin-left: 280px; padding: 30px; }
        
        .top-nav {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        
        .top-nav-title h1 { font-size: 28px; font-weight: 700; }
        .top-nav-user { display: flex; align-items: center; gap: 20px; }
        .user-avatar {
            width: 45px; height: 45px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .content-area {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }
        
        .form-actions { display: flex; gap: 10px; }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #f8f9fa; color: #666; border: 2px solid var(--border); }
        
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
        }
        .form-control:focus { outline: none; border-color: var(--primary); }
        
        .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        
        .image-upload {
            border: 2px dashed var(--border);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            position: relative;
            background: #f8f9fa;
        }
        
        .image-preview {
            margin-top: 20px;
            position: relative;
            display: inline-block;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 15px;
            border: 3px solid var(--border);
        }
        
        .remove-image {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
        }
        
        .slug-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .slug-preview {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 10px;
            border: 2px solid var(--border);
            flex: 1;
            font-family: monospace;
        }
        
        .generate-slug {
            background: #f8f9fa;
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 12px 20px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .admin-sidebar { width: 80px; }
            .admin-main { margin-left: 80px; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php $current_page = basename(__FILE__); include __DIR__ . '/includes/sidebar.php'; ?>
        <!-- Main Content -->
        <div class="admin-main">
            <div class="top-nav">
                <div class="top-nav-title">
                    <h1><?php echo $is_edit ? 'Edit Post' : 'Create New Post'; ?></h1>
                </div>
                <div class="top-nav-user">
                    <span><?php echo htmlspecialchars($admin_username); ?></span>
                    <div class="user-avatar"><i class="fas fa-user"></i></div>
                </div>
            </div>
            
            <div class="content-area">
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-error"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data" id="postForm">
                    <div class="form-header">
                        <h2>Post Details</h2>
                        <div class="form-actions">
                            <a href="posts.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="submit_post" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?php echo $is_edit ? 'Update' : 'Publish'; ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" id="title" class="form-control" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Category *</label>
                            <select name="category" class="form-control" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo ($post['category'] == $cat) ? 'selected' : ''; ?>>
                                        <?php echo $cat; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Slug</label>
                            <div class="slug-input-group">
                                <div class="slug-preview" id="slugPreview"><?php echo $post['slug'] ?: 'auto-generated'; ?></div>
                                <input type="hidden" name="slug" id="slugInput" value="<?php echo htmlspecialchars($post['slug']); ?>">
                                <button type="button" class="generate-slug" onclick="generateSlug()">
                                    <i class="fas fa-sync-alt"></i> Generate
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="draft" <?php echo $post['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo $post['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Content *</label>
                        <textarea name="content" id="content" class="form-control" rows="15" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Excerpt</label>
                        <textarea name="excerpt" class="form-control" rows="4"><?php echo htmlspecialchars($post['excerpt']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Featured Image</label>
                        <div class="image-upload" onclick="document.getElementById('featured_image').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload</p>
                            <input type="file" name="featured_image" id="featured_image" accept="image/*" onchange="previewImage(this)">
                        </div>
                        
                        <?php if (!empty($post['featured_image'])): ?>
                            <div class="image-preview" id="imagePreview">
                                <img src="../uploads/images/<?php echo $post['featured_image']; ?>" alt="Preview">
                                <button type="button" class="remove-image" onclick="removeImage()">×</button>
                            </div>
                        <?php else: ?>
                            <div class="image-preview" id="imagePreview" style="display: none;"></div>
                        <?php endif; ?>
                        
                        <input type="hidden" name="remove_image" id="remove_image" value="0">
                    </div>
                    
                    <?php if ($is_edit): ?>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-top: 20px;">
                            <small>Created: <?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></small>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
        
        $(document).ready(function() {
            $('#content').summernote({
                height: 500,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'clear']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview']]
                ]
            });
        });
        
        function generateSlug() {
            const title = document.getElementById('title').value;
            if (title) {
                const slug = title.toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
                
                document.getElementById('slugPreview').textContent = slug;
                document.getElementById('slugInput').value = slug;
            } else {
                alert('Please enter a title first');
            }
        }
        
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview"><button type="button" class="remove-image" onclick="removeImage()">×</button>`;
                    preview.style.display = 'inline-block';
                    document.getElementById('remove_image').value = '0';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function removeImage() {
            if (confirm('Remove this image?')) {
                document.getElementById('imagePreview').style.display = 'none';
                document.getElementById('imagePreview').innerHTML = '';
                document.getElementById('featured_image').value = '';
                document.getElementById('remove_image').value = '1';
            }
        }
        
        document.getElementById('title').addEventListener('input', function() {
            if (!document.getElementById('slugInput').value) {
                generateSlug();
            }
        });
    </script>
</body>
</html>