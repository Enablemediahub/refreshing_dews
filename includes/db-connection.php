<?php
/**
 * Database Connection File
 */

// Include config first
require_once 'config.php';

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// For functions that need global $conn
function getDBConnection() {
    global $conn;
    return $conn;
}
?>