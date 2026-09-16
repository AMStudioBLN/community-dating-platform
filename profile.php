<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

if (!INSTALLED) {
    header('Location: install.php');
    exit;
}

requireLogin();

$profile_user_id = (int)($_GET['id'] ?? 0);
$current_user_id = getCurrentUserId();

if ($profile_user_id <= 0) {
    $profile_user_id = $current_user_id;
}

$user = getUserById($profile_user_id);
if (!$user || !$user['is_active']) {
    header('Location: index.php');
    exit;
}

$profile = getUserProfile($profile_user_id);
if (!$profile) {
    header('Location: index.php');
    exit;
}

// Profilbesuch tracken
if ($profile_user_id != $current_user_id && $profile['show_profile_visitors']) {
    $pdo = db();
    $stmt = $pdo->prepare('
        INSERT INTO profile_visits (profile_user_id, visitor_user_id, visited_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE visited_at = NOW()
    ');
    $stmt->execute([$profile_user_id, $current_user_id]);
}

// Galerie laden
$pdo = db();
$stmt = $pdo->prepare('
    SELECT * FROM user_photos WHERE user_id = ? ORDER BY is_profile_picture DESC, created_at DESC
');
$stmt->execute([$profile_user_id]);
$photos = $stmt->fetchAll();

// Prüfe Like-Status
$stmt = $pdo->prepare('SELECT id FROM user_likes WHERE liker_user_id = ? AND liked_user_id = ?');
$stmt->execute([$current_user_id, $profile_user_id]);
$is_liked = $stmt->fetch() ? true : false;

// Prüfe Favorit-Status
$stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND favorite_user_id = ?');
$stmt->execute([$current_user_id, $profile_user_id]);
$is_favorite = $stmt->fetch() ? true : false;

// Prüfe Match-Status
$stmt = $pdo->prepare('
    SELECT id FROM user_matches 
    WHERE (user_id_1 = ? AND user_id_2 = ?) OR (user_id_1 = ? AND user_id_2 = ?)
');
$stmt->execute([$current_user_id, $profile_user_id, $profile_user_id, $current_user_id]);
$is_match = $stmt->fetch() ? true : false;

// Prüfe Block-Status
$stmt = $pdo->prepare('
    SELECT id FROM blocks 
    WHERE (blocker_user_id = ? AND blocked_user_id = ?) OR (blocker_user_id = ? AND blocked_user_id = ?)
');
$stmt->execute([$current_user_id, $profile_user_id, $profile_user_id, $current_user_id]);
$is_blocked = $stmt->fetch() ? true : false;

$age = calculateAge($profile['birthdate']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($user['username']); ?> - Profil</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <div class="profile-container">
            <!-- Profilheader -->
            <div class="profile-header">
                <div class="profile-cover"></div>
                <div class="profile-info">
                    <div class="profile-picture-section">
                        <?php 
                        $profile_pic = null;
                        foreach ($photos as $photo) {
                            if ($photo['is_profile_picture']) {
                                $profile_pic = $photo['filename'];
                                break;
                            }
                        }
                        ?>
                        <img src="<?php echo UPLOADS_URL; ?>/profiles/<?php echo $profile_pic ?? 'default.png'; ?>" 
                             alt="<?php echo escape($user['username']); ?>" class="profile-picture">
                    </div>
                    <div class="profile-details">
                        <h1><?php echo escape($user['username']); ?>, <?php echo $age; ?></h1>
                        <p class="profile-title"><?php echo escape($profile['title'] ?? 'Kein Titel'); ?></p>
                        <p class="profile-location">📍 <?php echo escape($profile['location'] ?? 'Standort nicht angegeben'); ?></p>
                        <div class="profile-stats">
                            <div class="stat">
                                <strong><?php echo $profile['profile_views']; ?></strong>
                                <span>Profilbesuche</span>
                            </div>
                            <div class="stat">
                                <?php 
                                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM user_likes WHERE liked_user_id = ?');
                                $stmt->execute([$profile_user_id]);
                                $likes = $stmt->fetch()['count'] ?? 0;
                                ?>
                                <strong><?php echo $likes; ?></strong>
                                <span>Likes</span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($profile_user_id != $current_user_id && !$is_blocked): ?>
                    <div class="profile-actions">
                        <button class="btn btn-primary" onclick="likeUser(<?php echo $profile_user_id; ?>)">
                            <?php echo $is_liked ? '❤️ Geliked' : '🤍 Liken'; ?>
                        </button>
                        <button class="btn btn-secondary" onclick="addFavorite(<?php echo $profile_user_id; ?>)">
                            <?php echo $is_favorite ? '⭐ Favorit' : '☆ Favorit'; ?>
                        </button>
                        <button class="btn btn-secondary" onclick="location.href='messages.php?id=<?php echo $profile_user_id; ?>'">
                            💬 Nachricht
                        </button>
                        <button class="btn btn-danger" onclick="blockUser(<?php echo $profile_user_id; ?>)">
                            🚫 Blockieren
                        </button>
                        <button class="btn btn-danger" onclick="reportUser(<?php echo $profile_user_id; ?>)">
                            🚩 Melden
                        </button>
                    </div>
                <?php elseif ($is_blocked): ?>
                    <div class="alert alert-warning">Sie haben diesen Benutzer blockiert</div>
                <?php else: ?>
                    <div class="profile-actions">
                        <a href="profile.php?id=<?php echo $current_user_id; ?>" class="btn btn-primary">Mein Profil</a>
                        <a href="profile.php?id=<?php echo $current_user_id; ?>?edit=1" class="btn btn-secondary">Bearbeiten</a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="profile-content">
                <!-- Über Mich -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h3>Über mich</h3>
                        <p><?php echo nl2br(escape($profile['about_me'] ?? 'Keine Beschreibung')); ?></p>
                    </div>
                </div>

                <!-- Interessen -->
                <?php if ($profile['interests']): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <h3>Interessen</h3>
                            <div class="tags">
                                <?php foreach (explode(',', $profile['interests']) as $interest): ?>
                                    <span class="tag"><?php echo escape(trim($interest)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Galerie -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h3>Fotos</h3>
                        <?php if (empty($photos)): ?>
                            <p>Keine Fotos vorhanden</p>
                        <?php else: ?>
                            <div class="photo-gallery">
                                <?php foreach ($photos as $photo): ?>
                                    <div class="photo-item">
                                        <img src="<?php echo UPLOADS_URL; ?>/gallery/<?php echo escape($photo['filename']); ?>" 
                                             alt="<?php echo escape($photo['title']); ?>" class="photo">
                                        <?php if ($photo['title']): ?>
                                            <p class="photo-title"><?php echo escape($photo['title']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
