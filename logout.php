<?php
session_start();
session_unset();
session_destroy();
// Redirect to the login page after logout
header("Location: admin.php");
exit();
?>
