<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

$user_id = getCurrentUserId();
$reported_user_id = (int)($_POST['user_id'] ?? 0);
$reason = sanitize($_POST['reason'] ?? '');

if ($reported_user_id <= 0 || $reported_user_id == $user_id || empty($reason)) {
    jsonResponse(false, 'Ungültige Anfrage');
}

// Prüfe ob bereits gemeldet
$pdo = db();
$stmt = $pdo->prepare('
    SELECT id FROM reports 
    WHERE reporter_user_id = ? AND reported_user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
');
$stmt->execute([$user_id, $reported_user_id]);

if ($stmt->fetch()) {
    jsonResponse(false, 'Sie haben diesen Benutzer bereits gemeldet');
}

$stmt = $pdo->prepare('
    INSERT INTO reports (reporter_user_id, reported_user_id, target_type, reason, description, status, created_at)
    VALUES (?, ?, ?, ?, ?, "pending", NOW())
');
$stmt->execute([$user_id, $reported_user_id, 'user', 'other', $reason]);

logAction($user_id, 'user_reported', $reason, $reported_user_id, 'user');

jsonResponse(true, 'Meldung erfolgreich eingereicht');
?>
