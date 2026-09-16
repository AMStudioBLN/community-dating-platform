<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

$user_id = getCurrentUserId();
$favorite_user_id = (int)($_POST['user_id'] ?? 0);

if ($favorite_user_id <= 0 || $favorite_user_id == $user_id) {
    jsonResponse(false, 'Ungültige Anfrage');
}

$pdo = db();

// Prüfe ob bereits in Favoriten
$stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND favorite_user_id = ?');
$stmt->execute([$user_id, $favorite_user_id]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND favorite_user_id = ?');
    $stmt->execute([$user_id, $favorite_user_id]);
    jsonResponse(true, 'Aus Favoriten entfernt');
} else {
    $stmt = $pdo->prepare('INSERT INTO favorites (user_id, favorite_user_id, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$user_id, $favorite_user_id]);
    jsonResponse(true, 'Zu Favoriten hinzugefügt');
}
?>
