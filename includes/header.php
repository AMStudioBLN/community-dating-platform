<?php
// Header Include Datei
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/responsive.css">
</head>
<body>
    <header class="header">
        <div class="header-container">
            <div class="logo">
                <a href="<?php echo SITE_URL; ?>/index.php">🌟 <?php echo SITE_NAME; ?></a>
            </div>

            <nav class="nav-main">
                <?php if (isLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/members.php">👥 Entdecken</a>
                    <a href="<?php echo SITE_URL; ?>/matches.php">💘 Matches</a>
                    <a href="<?php echo SITE_URL; ?>/messages.php">💬 Nachrichten</a>
                    <a href="<?php echo SITE_URL; ?>/groups.php">👨‍👩‍👧‍👦 Gruppen</a>
                    <a href="<?php echo SITE_URL; ?>/events.php">🎉 Events</a>
                    <a href="<?php echo SITE_URL; ?>/forum.php">💬 Forum</a>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php">Anmelden</a>
                    <a href="<?php echo SITE_URL; ?>/register.php">Registrieren</a>
                <?php endif; ?>
            </nav>

            <div class="header-right">
                <?php if (isLoggedIn()): ?>
                    <div class="header-icons">
                        <a href="<?php echo SITE_URL; ?>/notifications.php" class="icon-btn notification-icon">
                            🔔
                            <?php 
                            $unread = getUnreadNotificationCount(getCurrentUserId());
                            if ($unread > 0): 
                            ?>
                                <span class="badge"><?php echo $unread; ?></span>
                            <?php endif; ?>
                        </a>

                        <a href="<?php echo SITE_URL; ?>/messages.php" class="icon-btn message-icon">
                            💬
                            <?php 
                            $pdo = db();
                            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM messages WHERE recipient_id = ? AND is_read = 0');
                            $stmt->execute([getCurrentUserId()]);
                            $result = $stmt->fetch();
                            $unread_msgs = $result['count'] ?? 0;
                            if ($unread_msgs > 0): 
                            ?>
                                <span class="badge"><?php echo $unread_msgs; ?></span>
                            <?php endif; ?>
                        </a>
                    </div>

                    <div class="header-profile">
                        <button class="profile-btn" onclick="toggleProfileMenu()">👤</button>
                        <div class="dropdown-menu" id="profile-menu">
                            <a href="<?php echo SITE_URL; ?>/profile.php?id=<?php echo getCurrentUserId(); ?>">Mein Profil</a>
                            <a href="<?php echo SITE_URL; ?>/settings.php">⚙️ Einstellungen</a>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="<?php echo SITE_URL; ?>/admin.php">🛡️ Admin-Panel</a>
                            <?php endif; ?>
                            <hr>
                            <a href="<?php echo SITE_URL; ?>/logout.php">Abmelden</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">☰</button>
        </div>

        <?php if (isLoggedIn()): ?>
            <div class="mobile-menu" id="mobile-menu">
                <a href="<?php echo SITE_URL; ?>/members.php">👥 Entdecken</a>
                <a href="<?php echo SITE_URL; ?>/matches.php">💘 Matches</a>
                <a href="<?php echo SITE_URL; ?>/messages.php">💬 Nachrichten</a>
                <a href="<?php echo SITE_URL; ?>/groups.php">👨‍👩‍👧‍👦 Gruppen</a>
                <a href="<?php echo SITE_URL; ?>/events.php">🎉 Events</a>
                <a href="<?php echo SITE_URL; ?>/forum.php">💬 Forum</a>
                <a href="<?php echo SITE_URL; ?>/profile.php?id=<?php echo getCurrentUserId(); ?>">👤 Profil</a>
                <a href="<?php echo SITE_URL; ?>/settings.php">⚙️ Einstellungen</a>
                <a href="<?php echo SITE_URL; ?>/logout.php">Abmelden</a>
            </div>
        <?php endif; ?>
    </header>

    <div class="container-main">
