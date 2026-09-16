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

// Lade Benachrichtigungen
$stmt = $pdo->prepare('
    SELECT * FROM notifications 
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 50
');
$stmt->execute([$current_user_id]);
$notifications = $stmt->fetchAll();

// Zähle ungelesene
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$current_user_id]);
$unread_count = $stmt->fetch()['count'] ?? 0;

// Handle Mark as Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_all_read') {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([$current_user_id]);
    } elseif ($_POST['action'] === 'delete' && isset($_POST['notification_id'])) {
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$_POST['notification_id'], $current_user_id]);
    }
    header('Refresh: 0');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benachrichtigungen - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
        }
        .notification-item:hover {
            background: var(--light);
        }
        .notification-item.unread {
            background: #f0f4ff;
            border-left: 4px solid var(--primary);
        }
        .notification-content {
            flex: 1;
        }
        .notification-message {
            font-weight: 500;
            color: var(--text);
            margin-bottom: 0.25rem;
        }
        .notification-time {
            font-size: 0.85rem;
            color: var(--text-light);
        }
        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }
        .notification-actions form {
            margin: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1>🔔 Benachrichtigungen <?php if ($unread_count > 0): ?><span class="badge badge-primary"><?php echo $unread_count; ?></span><?php endif; ?></h1>
            <?php if (!empty($notifications)): ?>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="btn btn-secondary">Alle als gelesen markieren</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <p>🤐 Keine Benachrichtigungen</p>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body" style="padding: 0;">
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?php echo !$notif['is_read'] ? 'unread' : ''; ?>">
                            <div class="notification-content">
                                <div class="notification-message">
                                    <?php 
                                    $icon = '';
                                    $message = '';
                                    
                                    switch ($notif['type']) {
                                        case 'like':
                                            $icon = '❤️';
                                            $message = 'Hat Ihr Profil geliked';
                                            break;
                                        case 'match':
                                            $icon = '💘';
                                            $message = 'Sie haben einen neuen Match!';
                                            break;
                                        case 'message':
                                            $icon = '💬';
                                            $message = 'Hat Ihnen eine Nachricht gesendet';
                                            break;
                                        case 'visit':
                                            $icon = '👀';
                                            $message = 'Hat Ihr Profil besucht';
                                            break;
                                        case 'friend_request':
                                            $icon = '👫';
                                            $message = 'Hat Ihnen eine Freundschaftsanfrage gesendet';
                                            break;
                                        case 'group_invite':
                                            $icon = '👨‍👩‍👧‍👦';
                                            $message = 'Hat Sie zu einer Gruppe eingeladen';
                                            break;
                                        case 'event':
                                            $icon = '🎉';
                                            $message = 'Event-Benachrichtigung';
                                            break;
                                        default:
                                            $icon = '🔔';
                                            $message = 'Neue Benachrichtigung';
                                    }
                                    
                                    echo $icon . ' ' . $message;
                                    ?>
                                </div>
                                <div class="notification-time"><?php echo timeAgo($notif['created_at']); ?></div>
                            </div>
                            <div class="notification-actions">
                                <?php if ($notif['related_url']): ?>
                                    <a href="<?php echo $notif['related_url']; ?>" class="btn btn-small btn-primary">Ansehen</a>
                                <?php endif; ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                    <button type="submit" class="btn btn-small btn-danger">×</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
