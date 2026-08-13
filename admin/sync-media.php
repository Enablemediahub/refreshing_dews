<?php
require_once '../includes/config.php';
require_once '../includes/db-connection.php';
require_once '../includes/functions.php';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit();
}

$admin_id = $_SESSION['admin_id'] ?? 1;
$synced = 0;
$errors = 0;

// Scan images directory
$image_dir = '../uploads/images/';
if (file_exists($image_dir)) {
    $images = scandir($image_dir);
    foreach ($images as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($image_dir . $file)) {
            // Check if already in database
            $check = $conn->prepare("SELECT id FROM media WHERE file_path = ?");
            $path = 'uploads/images/' . $file;
            $check->bind_param("s", $path);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows === 0) {
                // Get file info
                $file_path = $image_dir . $file;
                $file_size = filesize($file_path);
                $file_type = mime_content_type($file_path);
                
                // Insert into database
                $insert = $conn->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $insert->bind_param("sssii", $file, $path, $file_type, $file_size, $admin_id);
                
                if ($insert->execute()) {
                    $synced++;
                } else {
                    $errors++;
                }
                $insert->close();
            }
            $check->close();
        }
    }
}

// Scan audio directory
$audio_dir = '../uploads/audio/';
if (file_exists($audio_dir)) {
    $audios = scandir($audio_dir);
    foreach ($audios as $file) {
        if ($file !== '.' && $file !== '..' && !is_dir($audio_dir . $file)) {
            // Check if already in database
            $check = $conn->prepare("SELECT id FROM media WHERE file_path = ?");
            $path = 'uploads/audio/' . $file;
            $check->bind_param("s", $path);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows === 0) {
                // Get file info
                $file_path = $audio_dir . $file;
                $file_size = filesize($file_path);
                $file_type = mime_content_type($file_path);
                
                // Insert into database
                $insert = $conn->prepare("INSERT INTO media (file_name, file_path, file_type, file_size, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $insert->bind_param("sssii", $file, $path, $file_type, $file_size, $admin_id);
                
                if ($insert->execute()) {
                    $synced++;
                } else {
                    $errors++;
                }
                $insert->close();
            }
            $check->close();
        }
    }
}

echo "Sync complete! Added $synced files to database. Errors: $errors";
echo "<br><br><a href='media.php'>Go to Media Library</a>";
?>