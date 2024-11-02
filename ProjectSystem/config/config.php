<?php
// Database credentials
if (!defined('DB_SERVER')) {
    define('DB_SERVER', 'localhost');
}

if (!defined('DB_USERNAME')) {
    define('DB_USERNAME', 'root');  // Change to your DB username
}

if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', '');      // Change to your DB password
}

if (!defined('DB_NAME')) {
    define('DB_NAME', 'dormio_db');
}


// Attempt to connect to MySQL database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("ERROR: Could not connect. " . $conn->connect_error);
}
?>
