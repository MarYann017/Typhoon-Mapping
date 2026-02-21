<?php
require_once __DIR__ . '/app/Helper.php';

// Check if user is authenticated
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/components/topNav.php';
require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sideNav.php';
?>


</html>

