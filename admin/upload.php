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
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if (isset($_FILES['file'])) {
    $upload_dir = '../uploads/images/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['file'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (in_array($extension, $allowed)) {
        $filename = 'summernote_' . time() . '_' . uniqid() . '.' . $extension;
        $target = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target)) {
            echo json_encode([
                'success' => true,
                'url' => '../uploads/images/' . $filename
            ]);
            exit();
        }
    }
}

echo json_encode(['success' => false]);
?>