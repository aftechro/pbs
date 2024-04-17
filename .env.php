<?php
// Database configuration, set with your own details
define('DB_HOST', 'localhost');
define('DB_USER', 'booking');
define('DB_PASSWORD', 'booking');
define('DB_NAME', 'booking');

// Establish database connection with error handling and SSL connection
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);
mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, 3306, NULL, MYSQLI_CLIENT_SSL);
if (mysqli_connect_errno()) {
    die("Connection failed: " . mysqli_connect_error());
}

// Function to escape user input for SQL query with additional checks
function escapeString($conn, $value) {
    if (is_object($conn) && property_exists($conn, 'server_info')) {
        return mysqli_real_escape_string($conn, $value);
    } else {
        die("Invalid database connection");
    }
}
?>
