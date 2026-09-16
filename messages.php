<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

if (!INSTALLED) {
    header('Location: install.php');
    exit;
}

requireLogin();

$current_user_id = getCurrentUserId();
$pdo = db();

// Lade Nachrichten-Threads
$stmt = $pdo->prepare('
    SELECT 
        mt.id,
        CASE 
            WHEN mt.user_id_1 = ? THEN mt.user_id_2
            ELSE mt.user_id_1
        END as other_user_id,
        u.username,
        up.profile_picture,
        mt.last_message_at,
        (SELECT COUNT(*) FROM messages WHERE thread_id = mt.id AND recipient_id = ? AND is_read = 0) as unread_count
    FROM message_threads mt
    JOIN users u ON (mt.user_id_1 = u.id OR mt.user_id_2 = u.id) AND u.id != ?
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE mt.user_id_1 = ? OR mt.user_id_2 = ?
    ORDER BY mt.last_message_at DESC
');
$stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
$threads = $stmt->fetchAll();

// Aktueller Thread
$selected_thread = null;
$messages = [];

if (isset($_GET['id'])) {
    $other_user_id = (int)$_GET['id'];
    
    // Finde oder erstelle Thread
    $stmt = $pdo->prepare('
        SELECT id FROM message_threads
        WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?)
    ');
    $stmt->execute([$current_user_id, $other_user_id, $other_user_id, $current_user_id]);
    $thread = $stmt->fetch();
    
    if ($thread) {
        $selected_thread = $thread['id'];
        
        // Lade Nachrichten
        $stmt = $pdo->prepare('
            SELECT m.*, u.username, up.profile_picture
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE m.thread_id = ?
            ORDER BY m.created_at ASC
        ');
        $stmt->execute([$selected_thread]);
        $messages = $stmt->fetchAll();
        
        // Markiere als gelesen
        $stmt = $pdo->prepare('
            UPDATE messages SET is_read = 1, read_at = NOW() 
            WHERE thread_id = ? AND recipient_id = ?
        ');
        $stmt->execute([$selected_thread, $current_user_id]);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nachrichten - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        .messages-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 1rem;
            height: 600px;
        }
        .threads-list {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow-y: auto;
            background: white;
        }
        .thread-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background 0.2s;
        }
        .thread-item:hover {
            background: var(--light);
        }
        .thread-item.active {
            background: #f0f4ff;
            border-left: 4px solid var(--primary);
        }
        .thread-username {
            font-weight: 600;
            color: var(--text);
        }
        .thread-preview {
            font-size: 0.85rem;
            color: var(--text-light);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .thread-unread {
            background: var(--primary);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .messages-content {
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            display: flex;
            flex-direction: column;
        }
        .messages-header {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
        }
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .message {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .message.sent {
            justify-content: flex-end;
        }
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            word-wrap: break-word;
        }
        .message.received .message-bubble {
            background: var(--light);
            color: var(--text);
        }
        .message.sent .message-bubble {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }
        .message-time {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.25rem;
        }
        .messages-input {
            padding: 1rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 0.5rem;
        }
        #message-input {
            flex: 1;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: 6px;
        }
        .empty-messages {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-light);
            text-align: center;
        }
        @media (max-width: 768px) {
            .messages-container {
                grid-template-columns: 1fr;
            }
            .threads-list {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>💬 Nachrichten</h1>

        <div class="messages-container">
            <!-- Threads Liste -->
            <div class="threads-list">
                <?php if (empty($threads)): ?>
                    <div class="empty-state" style="padding: 2rem;">
                        <p>Keine Nachrichten</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($threads as $thread): ?>
                        <a href="messages.php?id=<?php echo $thread['other_user_id']; ?>" class="thread-item <?php echo $selected_thread == $thread['id'] ? 'active' : ''; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="flex: 1;">
                                    <div class="thread-username"><?php echo escape($thread['username']); ?></div>
                                    <div class="thread-preview">Zuletzt: <?php echo $thread['last_message_at'] ? formatDateTime($thread['last_message_at']) : 'Nie'; ?></div>
                                </div>
                                <?php if ($thread['unread_count'] > 0): ?>
                                    <div class="thread-unread"><?php echo $thread['unread_count']; ?></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Messages Content -->
            <div class="messages-content">
                <?php if (!$selected_thread): ?>
                    <div class="empty-messages">
                        <p>Wählen Sie eine Konversation oder starten Sie eine neue</p>
                    </div>
                <?php else: ?>
                    <div class="messages-header">
                        <?php 
                        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
                        $stmt->execute([$_GET['id']]);
                        $other_user = $stmt->fetch();
                        echo 'Chat mit ' . escape($other_user['username']);
                        ?>
                    </div>

                    <div class="messages-area" id="messages-container">
                        <?php foreach ($messages as $message): ?>
                            <div class="message <?php echo $message['sender_id'] == $current_user_id ? 'sent' : 'received'; ?>">
                                <div>
                                    <div class="message-bubble"><?php echo escape($message['message']); ?></div>
                                    <div class="message-time"><?php echo formatDateTime($message['created_at']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="messages-input">
                        <input type="text" id="message-input" placeholder="Nachricht eingeben...">
                        <button class="btn btn-primary" onclick="sendMessage(<?php echo $_GET['id']; ?>)">Senden</button>
                    </div>

                    <script>
                        startMessageRefresh(<?php echo $selected_thread; ?>);
                        window.addEventListener('beforeunload', stopMessageRefresh);
                        
                        // Enter zum Senden
                        document.getElementById('message-input').addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                sendMessage(<?php echo $_GET['id']; ?>);
                            }
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
