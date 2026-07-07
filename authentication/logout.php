<?php
/**
 * authentication/logout.php
 * Destroys the session and returns the user to the login page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit;
