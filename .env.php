<?php
// Database configuration, set with your own details
define('DB_HOST', 'localhost');
define('DB_USER', 'booking');
define('DB_PASSWORD', 'booking');
define('DB_NAME', 'booking');

// Establish database connection with error handling
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Function to escape user input for SQL query with additional checks
function escapeString($conn, $value) {
    if ($conn && is_object($conn) && property_exists($conn, 'server_info')) {
        return mysqli_real_escape_string($conn, $value);
    } else {
        die("Invalid database connection");
    }
}
?>
