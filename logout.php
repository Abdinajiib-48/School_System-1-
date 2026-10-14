<?php
// ============================================================
// logout.php - Signs the person out and returns them to the login page.
//
// Who can open it: Anyone signed in
// ============================================================
include 'config.php';
$_SESSION = [];
session_destroy();
header('Location: index.php');
exit();
?>
