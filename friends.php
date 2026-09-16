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
$error = '';
$success = '';

// Lade Freundesliste
$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.online_status, up.birthdate, up.profile_picture
    FROM friendships f
    JOIN users u ON (f.user_id_1 = u.id OR f.user_id_2 = u.id) AND u.id != ?
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE (f.user_id_1 = ? OR f.user_id_2 = ?) AND f.status = "accepted"
    ORDER BY u.username
');
$stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
$friends = $stmt->fetchAll();

// Lade Freundschaftsanfragen
$stmt = $pdo->prepare('
    SELECT f.id, u.id as user_id, u.username, up.birthdate, up.profile_picture
    FROM friendships f
    JOIN users u ON f.requester_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE f.user_id_2 = ? AND f.status = "pending"
    ORDER BY f.created_at DESC
');
$stmt->execute([$current_user_id]);
$pending_requests = $stmt->fetchAll();

// Handle Anfrage Akzeptieren/Ablehnen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $friendship_id = (int)($_POST['friendship_id'] ?? 0);

    if ($action === 'accept') {
        $stmt = $pdo->prepare('UPDATE friendships SET status = "accepted" WHERE id = ? AND user_id_2 = ?');
        $stmt->execute([$friendship_id, $current_user_id]);
        $success = 'Freundschaftsanfrage akzeptiert';
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare('DELETE FROM friendships WHERE id = ? AND user_id_2 = ?');
        $stmt->execute([$friendship_id, $current_user_id]);
        $success = 'Freundschaftsanfrage abgelehnt';
    }
    
    header('Refresh: 2; url=friends.php');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Freunde - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>👫 Meine Freunde</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Freundschaftsanfragen -->
        <?php if (!empty($pending_requests)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h2>📬 Freundschaftsanfragen (<?php echo count($pending_requests); ?>)</h2>
                </div>
                <div class="card-body">
                    <div class="members-grid">
                        <?php foreach ($pending_requests as $request): ?>
                            <div class="member-card">
                                <div class="member-image">
                                    <?php 
                                    $stmt = $pdo->prepare('SELECT filename FROM user_photos WHERE user_id = ? AND is_profile_picture = 1 LIMIT 1');
                                    $stmt->execute([$request['user_id']]);
                                    $photo = $stmt->fetch();
                                    $profile_pic = $photo ? $photo['filename'] : 'default.png';
                                    ?>
                                    <img src="<?php echo UPLOADS_URL; ?>/profiles/<?php echo $profile_pic; ?>" alt="<?php echo escape($request['username']); ?>">
                                    <div class="member-overlay">
                                        <a href="profile.php?id=<?php echo $request['user_id']; ?>" class="btn btn-primary">Profil</a>
                                    </div>
                                </div>
                                <div class="member-info">
                                    <h3><?php echo escape($request['username']); ?></h3>
                                    <p class="member-age"><?php echo calculateAge($request['birthdate']); ?> Jahre</p>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="action" value="accept">
                                            <input type="hidden" name="friendship_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" class="btn btn-success" style="width: 100%;">✓ Akzeptieren</button>
                                        </form>
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="friendship_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="width: 100%;">✗ Ablehnen</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Freundesliste -->
        <div class="card">
            <div class="card-header">
                <h2>👥 Freunde (<?php echo count($friends); ?>)</h2>
            </div>
            <div class="card-body">
                <?php if (empty($friends)): ?>
                    <div class="empty-state">
                        <p>Sie haben noch keine Freunde. Senden Sie Freundschaftsanfragen!</p>
                    </div>
                <?php else: ?>
                    <div class="members-grid">
                        <?php foreach ($friends as $friend): ?>
                            <div class="member-card">
                                <div class="member-image">
                                    <?php 
                                    $stmt = $pdo->prepare('SELECT filename FROM user_photos WHERE user_id = ? AND is_profile_picture = 1 LIMIT 1');
                                    $stmt->execute([$friend['id']]);
                                    $photo = $stmt->fetch();
                                    $profile_pic = $photo ? $photo['filename'] : 'default.png';
                                    ?>
                                    <img src="<?php echo UPLOADS_URL; ?>/profiles/<?php echo $profile_pic; ?>" alt="<?php echo escape($friend['username']); ?>">
                                    <div class="member-overlay">
                                        <a href="profile.php?id=<?php echo $friend['id']; ?>" class="btn btn-primary">Profil</a>
                                        <a href="messages.php?id=<?php echo $friend['id']; ?>" class="btn btn-secondary">💬 Chat</a>
                                    </div>
                                    <?php if ($friend['online_status'] === 'online'): ?>
                                        <div class="online-indicator">🟢 Online</div>
                                    <?php endif; ?>
                                </div>
                                <div class="member-info">
                                    <h3><?php echo escape($friend['username']); ?></h3>
                                    <p class="member-age"><?php echo calculateAge($friend['birthdate']); ?> Jahre</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
