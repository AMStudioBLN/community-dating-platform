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

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// Lade Matches
$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.last_activity, up.birthdate, up.profile_picture, up.location, up.title
    FROM user_matches um
    JOIN users u ON (um.user_id_1 = u.id OR um.user_id_2 = u.id) AND u.id != ?
    LEFT JOIN user_profiles up ON u.id = up.user_id
    WHERE um.user_id_1 = ? OR um.user_id_2 = ?
    ORDER BY um.created_at DESC
    LIMIT ?, ?
');

$offset = ($page - 1) * $per_page;
$stmt->execute([$current_user_id, $current_user_id, $current_user_id, $offset, $per_page]);
$matches = $stmt->fetchAll();

// Zähle Matches
$stmt = $pdo->prepare('
    SELECT COUNT(*) as total FROM user_matches 
    WHERE user_id_1 = ? OR user_id_2 = ?
');
$stmt->execute([$current_user_id, $current_user_id]);
$total = $stmt->fetch()['total'] ?? 0;

$pagination = getPagination($page, $per_page, $total);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meine Matches - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>💘 Meine Matches</h1>
        <p>Hier sind Ihre gegenseitigen Matches. Treten Sie in Kontakt!</p>

        <?php if (empty($matches)): ?>
            <div class="empty-state">
                <p>🌟 Sie haben noch keine Matches. Gehen Sie zu "Entdecken" und liken Sie Profile!</p>
                <a href="members.php" class="btn btn-primary">Mitglieder entdecken</a>
            </div>
        <?php else: ?>
            <div class="members-grid">
                <?php foreach ($matches as $match): ?>
                    <div class="member-card">
                        <div class="member-image">
                            <?php 
                            $stmt = $pdo->prepare('SELECT filename FROM user_photos WHERE user_id = ? AND is_profile_picture = 1 LIMIT 1');
                            $stmt->execute([$match['id']]);
                            $photo = $stmt->fetch();
                            $profile_pic = $photo ? $photo['filename'] : 'default.png';
                            ?>
                            <img src="<?php echo UPLOADS_URL; ?>/profiles/<?php echo $profile_pic; ?>" alt="<?php echo escape($match['username']); ?>">
                            <div class="member-overlay">
                                <a href="profile.php?id=<?php echo $match['id']; ?>" class="btn btn-primary">Profil</a>
                                <a href="messages.php?id=<?php echo $match['id']; ?>" class="btn btn-secondary">💬 Chat</a>
                            </div>
                        </div>
                        <div class="member-info">
                            <h3><a href="profile.php?id=<?php echo $match['id']; ?>"><?php echo escape($match['username']); ?></a></h3>
                            <p class="member-age"><?php echo calculateAge($match['birthdate']); ?> Jahre</p>
                            <?php if ($match['location']): ?>
                                <p class="member-location">📍 <?php echo escape($match['location']); ?></p>
                            <?php endif; ?>
                            <p class="member-activity">Zuletzt: <?php echo timeAgo($match['last_activity']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($pagination['page'] > 1): ?>
                        <a href="?page=1" class="btn btn-small">« Erste</a>
                        <a href="?page=<?php echo $pagination['page'] - 1; ?>" class="btn btn-small">‹ Zurück</a>
                    <?php endif; ?>
                    <span class="pagination-info">Seite <?php echo $pagination['page']; ?> von <?php echo $pagination['total_pages']; ?></span>
                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                        <a href="?page=<?php echo $pagination['page'] + 1; ?>" class="btn btn-small">Weiter ›</a>
                        <a href="?page=<?php echo $pagination['total_pages']; ?>" class="btn btn-small">Letzte »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
