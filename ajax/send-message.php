<?php
require_once '../config.php';
require_once '../database.php';
require_once '../functions.php';

requireLogin();

header('Content-Type: application/json');

$user_id = getCurrentUserId();
$recipient_id = (int)($_POST['recipient_id'] ?? 0);
$message = sanitize($_POST['message'] ?? '');

if ($recipient_id <= 0 || $recipient_id == $user_id || empty($message)) {
    jsonResponse(false, 'Ungültige Anfrage');
}

// Prüfe ob blockiert
$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM blocks WHERE (blocker_user_id = ? AND blocked_user_id = ?) OR (blocker_user_id = ? AND blocked_user_id = ?)');
$stmt->execute([$recipient_id, $user_id, $user_id, $recipient_user_id]);
if ($stmt->fetch()) {
    jsonResponse(false, 'Sie können dieser Person keine Nachricht senden');
}

// Finde oder erstelle Thread
$stmt = $pdo->prepare('
    SELECT id FROM message_threads 
    WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?)
');
$stmt->execute([$user_id, $recipient_id, $recipient_id, $user_id]);
$thread = $stmt->fetch();

if (!$thread) {
    $stmt = $pdo->prepare('
        INSERT INTO message_threads (user_id_1, user_id_2, created_at)
        VALUES (?, ?, NOW())
    ');
    $stmt->execute([min($user_id, $recipient_id), max($user_id, $recipient_id)]);
    $thread_id = $pdo->lastInsertId();
} else {
    $thread_id = $thread['id'];
}

// Nachricht speichern
$stmt = $pdo->prepare('
    INSERT INTO messages (thread_id, sender_id, recipient_id, message, created_at)
    VALUES (?, ?, ?, ?, NOW())
');
$stmt->execute([$thread_id, $user_id, $recipient_id, $message]);

// Thread aktualisieren
$stmt = $pdo->prepare('
    UPDATE message_threads SET last_message_at = NOW() WHERE id = ?
');
$stmt->execute([$thread_id]);

// Benachrichtigung
addNotification($recipient_id, 'message', $user_id, 'Neue Nachricht', 'messages.php');

logAction($user_id, 'message_sent', '', $recipient_id, 'user');

jsonResponse(true, 'Nachricht gesendet');
?>
