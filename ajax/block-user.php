<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

$user_id = getCurrentUserId();
$blocked_user_id = (int)($_POST['user_id'] ?? 0);

if ($blocked_user_id <= 0 || $blocked_user_id == $user_id) {
    jsonResponse(false, 'Ungültige Anfrage');
}

$pdo = db();

// Prüfe ob bereits blockiert
$stmt = $pdo->prepare('SELECT id FROM blocks WHERE blocker_user_id = ? AND blocked_user_id = ?');
$stmt->execute([$user_id, $blocked_user_id]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare('DELETE FROM blocks WHERE blocker_user_id = ? AND blocked_user_id = ?');
    $stmt->execute([$user_id, $blocked_user_id]);
    logAction($user_id, 'user_unblocked', '', $blocked_user_id, 'user');
    jsonResponse(true, 'Blockierung aufgehoben');
} else {
    $stmt = $pdo->prepare('INSERT INTO blocks (blocker_user_id, blocked_user_id, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$user_id, $blocked_user_id]);
    logAction($user_id, 'user_blocked', '', $blocked_user_id, 'user');
    jsonResponse(true, 'Benutzer blockiert');
}
?>
