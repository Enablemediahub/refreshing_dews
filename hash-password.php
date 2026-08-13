<?php
// Save this as hash-password.php in your refreshing_dews folder
// Delete it after use for security

require_once 'includes/config.php';
require_once 'includes/db-connection.php';

$password = 'admin123'; // Change this to your desired password
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Password: " . $password . "<br>";
echo "Hash: " . $hash . "<br>";
echo "<br>Run this SQL in phpMyAdmin:<br>";
echo "UPDATE users SET password = '" . $hash . "' WHERE username = 'admin';";
?>