<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

if (!INSTALLED) {
    header('Location: install.php');
    exit;
}

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die('Datenbankverbindung fehlgeschlagen. Bitte führen Sie das <a href="install.php">Setup</a> durch.');
}

// Benutzer aktualisieren, falls eingeloggt
if (isLoggedIn()) {
    $user = getUserById(getCurrentUserId());
    if (!$user) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    // Online-Status aktualisieren
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET last_activity = NOW(), online_status = "online" WHERE id = ?');
    $stmt->execute([getCurrentUserId()]);
}

// Feed-Daten laden
$feed_posts = [];
$unread_notifications = 0;
$unread_messages = 0;

if (isLoggedIn()) {
    $unread_notifications = getUnreadNotificationCount(getCurrentUserId());
    
    // Ungelesene Nachrichten zählen
    $pdo = db();
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as count FROM messages 
        WHERE recipient_id = ? AND is_read = 0
    ');
    $stmt->execute([getCurrentUserId()]);
    $result = $stmt->fetch();
    $unread_messages = $result['count'] ?? 0;
    
    // Feed-Beiträge laden
    $stmt = $pdo->prepare('
        SELECT fp.*, u.username, up.profile_picture
        FROM feed_posts fp
        JOIN users u ON fp.user_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        ORDER BY fp.created_at DESC
        LIMIT 20
    ');
    $stmt->execute();
    $feed_posts = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Community & Dating Plattform</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <?php if (!isLoggedIn()): ?>
            <!-- Landing Page -->
            <section class="hero">
                <div class="hero-content">
                    <h1>Willkommen bei <?php echo SITE_NAME; ?></h1>
                    <p>Finde neue Kontakte, knüpfe Freundschaften und triff interessante Menschen</p>
                    <div class="hero-buttons">
                        <a href="register.php" class="btn btn-primary btn-large">Jetzt registrieren</a>
                        <a href="login.php" class="btn btn-secondary btn-large">Anmelden</a>
                    </div>
                </div>
                <div class="hero-image">
                    <div class="hero-placeholder">👥 Community Hub</div>
                </div>
            </section>

            <section class="features">
                <h2>Was macht uns besonders?</h2>
                <div class="feature-grid">
                    <div class="feature-card">
                        <div class="feature-icon">💘</div>
                        <h3>Matching-System</h3>
                        <p>Finde kompatible Partner durch intelligente Matches</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💬</div>
                        <h3>Live Chat</h3>
                        <p>Echtzeitkommunikation mit anderen Mitgliedern</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">👥</div>
                        <h3>Gruppen & Events</h3>
                        <p>Tritt Communities bei und besuche Events</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔒</div>
                        <h3>Sicher & Privat</h3>
                        <p>Deine Privatsphäre ist uns wichtig</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📱</div>
                        <h3>Überall erreichbar</h3>
                        <p>Mobile-optimiert für unterwegs</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🎯</div>
                        <h3>Personalisiert</h3>
                        <p>Maßgeschneidert nach deinen Interessen</p>
                    </div>
                </div>
            </section>

            <section class="cta-section">
                <h2>Bereit zu starten?</h2>
                <p>Registriere dich kostenlos und finde neue Kontakte</p>
                <a href="register.php" class="btn btn-primary btn-large">Kostenlos anmelden</a>
            </section>

        <?php else: ?>
            <!-- Dashboard für angemeldete Benutzer -->
            <div class="dashboard">
                <div class="main-content">
                    <section class="feed-section">
                        <h2>Aktivitäts-Feed</h2>
                        
                        <!-- Feed-Beitrag erstellen -->
                        <div class="create-post">
                            <div class="post-header">
                                <img src="<?php echo UPLOADS_URL; ?>/profiles/<?php echo getUserProfile(getCurrentUserId())['profile_picture'] ?? 'default.png'; ?>" alt="Profil" class="avatar-small">
                                <input type="text" placeholder="Was geht dir durch den Kopf?" class="post-input" onclick="document.getElementById('post-modal').style.display='block'">
                            </div>
                        </div>

                        <!-- Feed-Beiträge -->
                        <?php if (empty($feed_posts)): ?>
                            <div class="empty-state">
                                <p>🌟 Noch keine Beiträge. Starte das erste Gespräch!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($feed_posts as $post): ?>
                                <div class="feed-post">
                                    <div class="post-header">
                                        <div class="post-user">
                                            <img src="<?php echo UPLOADS_URL; ?>/profiles/<?php echo $post['profile_picture'] ?? 'default.png'; ?>" alt="<?php echo $post['username']; ?>" class="avatar-small">
                                            <div>
                                                <a href="profile.php?id=<?php echo $post['user_id']; ?>" class="username"><?php echo escape($post['username']); ?></a>
                                                <span class="timestamp"><?php echo timeAgo($post['created_at']); ?></span>
                                            </div>
                                        </div>
                                        <?php if ($post['user_id'] == getCurrentUserId()): ?>
                                            <button class="post-menu" onclick="showPostMenu(<?php echo $post['id']; ?>)">⋮</button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-content">
                                        <p><?php echo escape($post['content']); ?></p>
                                        <?php if ($post['image']): ?>
                                            <img src="<?php echo UPLOADS_URL; ?>/gallery/<?php echo $post['image']; ?>" alt="Bild" class="post-image">
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-actions">
                                        <button class="action-btn" onclick="likePost(<?php echo $post['id']; ?>)">
                                            👍 <?php echo $post['like_count']; ?>
                                        </button>
                                        <button class="action-btn" onclick="commentPost(<?php echo $post['id']; ?>)">
                                            💬 <?php echo $post['comment_count']; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- Sidebar -->
                <aside class="sidebar">
                    <div class="sidebar-widget">
                        <h3>👋 Hallo!</h3>
                        <p><?php echo escape(getUserById(getCurrentUserId())['username']); ?></p>
                        <a href="profile.php?id=<?php echo getCurrentUserId(); ?>" class="btn btn-small">Mein Profil</a>
                    </div>

                    <div class="sidebar-widget">
                        <h3>🎯 Schnelle Links</h3>
                        <ul class="quick-links">
                            <li><a href="members.php">👥 Mitglieder entdecken</a></li>
                            <li><a href="matches.php">💘 Meine Matches</a></li>
                            <li><a href="messages.php">💬 Nachrichten</a></li>
                            <li><a href="friends.php">👫 Freunde</a></li>
                            <li><a href="groups.php">👨‍👩‍👧‍👦 Gruppen</a></li>
                            <li><a href="events.php">🎉 Events</a></li>
                            <li><a href="forum.php">💬 Forum</a></li>
                        </ul>
                    </div>

                    <div class="sidebar-widget">
                        <h3>📊 Statistiken</h3>
                        <div class="stat-item">
                            <span>Profilbesuche</span>
                            <strong>
                                <?php 
                                $pdo = db();
                                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM profile_visits WHERE profile_user_id = ?');
                                $stmt->execute([getCurrentUserId()]);
                                $result = $stmt->fetch();
                                echo $result['count'] ?? 0;
                                ?>
                            </strong>
                        </div>
                        <div class="stat-item">
                            <span>Likes erhalten</span>
                            <strong>
                                <?php 
                                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM user_likes WHERE liked_user_id = ?');
                                $stmt->execute([getCurrentUserId()]);
                                $result = $stmt->fetch();
                                echo $result['count'] ?? 0;
                                ?>
                            </strong>
                        </div>
                        <div class="stat-item">
                            <span>Matches</span>
                            <strong>
                                <?php 
                                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM user_matches WHERE user_id_1 = ? OR user_id_2 = ?');
                                $stmt->execute([getCurrentUserId(), getCurrentUserId()]);
                                $result = $stmt->fetch();
                                echo $result['count'] ?? 0;
                                ?>
                            </strong>
                        </div>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
