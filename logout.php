<?php
require_once 'config.php';
require_once 'functions.php';

if (isLoggedIn()) {
    $user_id = getCurrentUserId();
    logAction($user_id, 'logout', '', $user_id, 'user');
}

// Session löschen
session_destroy();

header('Location: index.php');
exit;
?>