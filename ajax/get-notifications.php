<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

$user_id = getCurrentUserId();

$pdo = db();
$stmt = $pdo->prepare('
    SELECT n.*, u.username, up.profile_picture
    FROM notifications n
    LEFT JOIN users u ON n.related_user_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 10
');
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

$unread_count = getUnreadNotificationCount($user_id);

jsonResponse(true, '', [
    'notifications' => $notifications,
    'count' => $unread_count
]);
?>
