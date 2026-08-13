<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Upload Directory Check</h1>";

$base_dir = dirname(__DIR__);
$upload_dir = $base_dir . '/uploads';
$images_dir = $upload_dir . '/images';

echo "<p>Base directory: " . $base_dir . "</p>";
echo "<p>Uploads directory: " . $upload_dir . "</p>";
echo "<p>Images directory: " . $images_dir . "</p>";

// Check and create uploads directory
if (!is_dir($upload_dir)) {
    echo "<p style='color: orange;'>Uploads directory doesn't exist. Creating...</p>";
    if (mkdir($upload_dir, 0777, true)) {
        echo "<p style='color: green;'>✓ Uploads directory created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create uploads directory</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Uploads directory exists</p>";
}

// Check and create images directory
if (!is_dir($images_dir)) {
    echo "<p style='color: orange;'>Images directory doesn't exist. Creating...</p>";
    if (mkdir($images_dir, 0777, true)) {
        echo "<p style='color: green;'>✓ Images directory created successfully</p>";
    } else {
        echo "<p style='color: red;'>✗ Failed to create images directory</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Images directory exists</p>";
}

// Check write permissions
echo "<h2>Write Permission Test</h2>";

$test_file = $images_dir . '/test.txt';
if (file_put_contents($test_file, 'Test write permission')) {
    echo "<p style='color: green;'>✓ Write permission successful!</p>";
    unlink($test_file);
    echo "<p style='color: green;'>✓ Test file removed</p>";
} else {
    echo "<p style='color: red;'>✗ Cannot write to images directory. Please check permissions.</p>";
    echo "<p>On Windows: Right-click the 'uploads' folder > Properties > Security > Add 'Everyone' with Write permissions</p>";
}

// Check existing files
echo "<h2>Existing Images</h2>";
if (is_dir($images_dir)) {
    $files = scandir($images_dir);
    $images = array_diff($files, ['.', '..']);
    if (count($images) > 0) {
        echo "<ul>";
        foreach ($images as $image) {
            echo "<li>" . htmlspecialchars($image) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No images found in the directory.</p>";
    }
}

echo "<h2>Recommended Fix</h2>";
echo "<p>If you're having permission issues, run this in Command Prompt as Administrator:</p>";
echo "<pre>icacls \"C:\\xampp\\htdocs\\refreshing_dews\\uploads\" /grant Everyone:F /T</pre>";
echo "<p>Or manually set permissions:</p>";
echo "<ol>";
echo "<li>Right-click the 'uploads' folder in C:\\xampp\\htdocs\\refreshing_dews\\</li>";
echo "<li>Select Properties</li>";
echo "<li>Go to Security tab</li>";
echo "<li>Click Edit</li>";
echo "<li>Add 'Everyone' and give Full Control</li>";
echo "<li>Click Apply and OK</li>";
echo "</ol>";
?>