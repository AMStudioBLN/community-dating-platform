<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

$notification_id = (int)($_POST['notification_id'] ?? 0);
$user_id = getCurrentUserId();

if ($notification_id <= 0) {
    jsonResponse(false, 'Ungültige Anfrage');
}

markNotificationAsRead($notification_id, $user_id);
jsonResponse(true, 'Benachrichtigung als gelesen markiert');
?>
