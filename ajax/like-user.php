<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

$user_id = getCurrentUserId();
$liked_user_id = (int)($_POST['user_id'] ?? 0);

if ($liked_user_id <= 0 || $liked_user_id == $user_id) {
    jsonResponse(false, 'Ungültige Anfrage');
}

$pdo = db();

// Prüfe ob bereits geliked
$stmt = $pdo->prepare('SELECT id FROM user_likes WHERE liker_user_id = ? AND liked_user_id = ?');
$stmt->execute([$user_id, $liked_user_id]);
$existing_like = $stmt->fetch();

if ($existing_like) {
    // Like entfernen
    $stmt = $pdo->prepare('DELETE FROM user_likes WHERE liker_user_id = ? AND liked_user_id = ?');
    $stmt->execute([$user_id, $liked_user_id]);
    jsonResponse(true, 'Like entfernt');
} else {
    // Like hinzufügen
    $stmt = $pdo->prepare('INSERT INTO user_likes (liker_user_id, liked_user_id, created_at) VALUES (?, ?, NOW())');
    $stmt->execute([$user_id, $liked_user_id]);

    // Prüfe auf gegenseitiges Like für Match
    $stmt = $pdo->prepare('SELECT id FROM user_likes WHERE liker_user_id = ? AND liked_user_id = ?');
    $stmt->execute([$liked_user_id, $user_id]);
    $reverse_like = $stmt->fetch();

    if ($reverse_like) {
        // Gegenseitiges Like - Match erstellen
        $stmt = $pdo->prepare('
            INSERT INTO user_matches (user_id_1, user_id_2, created_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ');
        $stmt->execute([
            min($user_id, $liked_user_id),
            max($user_id, $liked_user_id)
        ]);

        // Benachrichtigung für anderen Benutzer
        addNotification($liked_user_id, 'match', $user_id, 'Du hast einen neuen Match!', 'matches.php');
    }

    // Benachrichtigung
    addNotification($liked_user_id, 'like', $user_id, 'Jemand mag dich!', 'members.php');

    logAction($user_id, 'user_liked', '', $liked_user_id, 'user');
    jsonResponse(true, 'Like hinzugefügt');
}
?>
