<?php

// Database connection
$host = "127.0.0.1";
$username = "root";
$password = "";
$database = "inventory_db";
$port = 3306;

$conn = new mysqli($host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Check if user is logged in
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}


// Clean user input
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}