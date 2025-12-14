<?php
//logout.php
session_start();
session_destroy();

// Clear all session variables
$_SESSION = array();

// If using session cookies, delete them
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: index.php");
exit();
?>
