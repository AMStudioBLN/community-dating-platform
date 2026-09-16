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

// Filter
$age_min = (int)($_GET['age_min'] ?? 18);
$age_max = (int)($_GET['age_max'] ?? 100);
$gender = sanitize($_GET['gender'] ?? '');
$looking_for = sanitize($_GET['looking_for'] ?? '');
$online_only = isset($_GET['online_only']);
$with_photo = isset($_GET['with_photo']);
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;

// Query bauen
$where = ['up.is_public = 1', 'u.id != ?', 'u.is_active = 1', 'u.is_blocked = 0'];
$params = [$current_user_id];

if (!empty($gender)) {
    $where[] = 'up.gender = ?';
    $params[] = $gender;
}

if (!empty($looking_for)) {
    $where[] = '(up.looking_for = ? OR up.looking_for = "both")';
    $params[] = $looking_for;
}

if ($age_min > 0) {
    $where[] = 'YEAR(CURDATE()) - YEAR(up.birthdate) >= ?';
    $params[] = $age_min;
}

if ($age_max < 100) {
    $where[] = 'YEAR(CURDATE()) - YEAR(up.birthdate) <= ?';
    $params[] = $age_max;
}

if ($online_only) {
    $where[] = 'u.online_status = "online"';
}

if ($with_photo) {
    $where[] = 'EXISTS (SELECT 1 FROM user_photos WHERE user_id = u.id AND is_profile_picture = 1)';
}

// Blockierte Benutzer ausschließen
$where[] = 'NOT EXISTS (
    SELECT 1 FROM blocks 
    WHERE (blocker_user_id = ? AND blocked_user_id = u.id) 
    OR (blocker_user_id = u.id AND blocked_user_id = ?)
)';
$params[] = $current_user_id;
$params[] = $current_user_id;

$where_clause = implode(' AND ', $where);

// Zähle Ergebnisse
$count_query = 'SELECT COUNT(*) as total FROM users u JOIN user_profiles up ON u.id = up.user_id WHERE ' . $where_clause;
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total = $stmt->fetch()['total'] ?? 0;

$pagination = getPagination($page, $per_page, $total);

// Benutzer laden
$query = '
    SELECT u.id, u.username, u.online_status, up.gender, up.birthdate, up.profile_picture, up.location, up.about_me, up.title
    FROM users u 
    JOIN user_profiles up ON u.id = up.user_id 
    WHERE ' . $where_clause . '
    ORDER BY u.last_activity DESC
    LIMIT ?, ?
';

$stmt = $pdo->prepare($query);
$stmt->execute(array_merge($params, [$pagination['offset'], $pagination['per_page']]));
$members = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitglieder - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>👥 Mitglieder entdecken</h1>

        <!-- Suchfilter -->
        <div class="card mb-3">
            <div class="card-body">
                <form id="search-form" method="GET" class="search-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="age_min">Alter von</label>
                            <input type="number" id="age_min" name="age_min" value="<?php echo $age_min; ?>" min="18" max="100">
                        </div>
                        <div class="form-group">
                            <label for="age_max">bis</label>
                            <input type="number" id="age_max" name="age_max" value="<?php echo $age_max; ?>" min="18" max="100">
                        </div>
                        <div class="form-group">
                            <label for="gender">Geschlecht</label>
                            <select id="gender" name="gender">
                                <option value="">-- Alle --</option>
                                <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Männlich</option>
                                <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Weiblich</option>
                                <option value="other" <?php echo $gender === 'other' ? 'selected' : ''; ?>>Sonstiges</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="looking_for">Suche nach</label>
                            <select id="looking_for" name="looking_for">
                                <option value="">-- Alle --</option>
                                <option value="male" <?php echo $looking_for === 'male' ? 'selected' : ''; ?>>Männlich</option>
                                <option value="female" <?php echo $looking_for === 'female' ? 'selected' : ''; ?>>Weiblich</option>
                                <option value="both" <?php echo $looking_for === 'both' ? 'selected' : ''; ?>>Beide</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group checkbox">
                            <input type="checkbox" id="online_only" name="online_only" <?php echo $online_only ? 'checked' : ''; ?>>
                            <label for="online_only">Nur Online</label>
                        </div>
                        <div class="form-group checkbox">
                            <input type="checkbox" id="with_photo" name="with_photo" <?php echo $with_photo ? 'checked' : ''; ?>>
                            <label for="with_photo">Mit Foto</label>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">🔍 Suchen</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Ergebnisse -->
        <?php if (empty($members)): ?>
            <div class="empty-state">
                <p>Keine Mitglieder gefunden, die Ihren Kriterien entsprechen.</p>
            </div>
        <?php else: ?>
            <div class="members-grid">
                <?php foreach ($members as $member): ?>
                    <div class="member-card">
                        <div class="member-image">
                            <?php 
                            $profile_pic = 'default.png';
                            $stmt = $pdo->prepare('SELECT filename FROM user_photos WHERE user_id = ? AND is_profile_picture = 1 LIMIT 1');
                            $stmt->execute([$member['id']]);
                            $photo = $stmt->fetch();
                            if ($photo) {
                                $profile_pic = $photo['filename'];
                            }
                            ?>
                            <img src="<?php echo UPLOADS_URL; ?>/profiles/<?php echo $profile_pic; ?>" alt="<?php echo escape($member['username']); ?>">
                            <div class="member-overlay">
                                <a href="profile.php?id=<?php echo $member['id']; ?>" class="btn btn-primary">Profil ansehen</a>
                                <button class="btn btn-secondary" onclick="likeUser(<?php echo $member['id']; ?>)">❤️ Liken</button>
                            </div>
                            <?php if ($member['online_status'] === 'online'): ?>
                                <div class="online-indicator">🟢 Online</div>
                            <?php endif; ?>
                        </div>
                        <div class="member-info">
                            <h3><a href="profile.php?id=<?php echo $member['id']; ?>"><?php echo escape($member['username']); ?></a></h3>
                            <p class="member-age"><?php echo calculateAge($member['birthdate']); ?> Jahre</p>
                            <?php if ($member['location']): ?>
                                <p class="member-location">📍 <?php echo escape($member['location']); ?></p>
                            <?php endif; ?>
                            <?php if ($member['title']): ?>
                                <p class="member-title">"<?php echo escape($member['title']); ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagierung -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination">
                    <?php if ($pagination['page'] > 1): ?>
                        <a href="?page=1<?php echo isset($_GET['age_min']) ? '&age_min=' . $age_min : ''; ?><?php echo isset($_GET['age_max']) ? '&age_max=' . $age_max : ''; ?>" class="btn btn-small">« Erste</a>
                        <a href="?page=<?php echo $pagination['page'] - 1; ?><?php echo isset($_GET['age_min']) ? '&age_min=' . $age_min : ''; ?><?php echo isset($_GET['age_max']) ? '&age_max=' . $age_max : ''; ?>" class="btn btn-small">‹ Zurück</a>
                    <?php endif; ?>

                    <span class="pagination-info">Seite <?php echo $pagination['page']; ?> von <?php echo $pagination['total_pages']; ?></span>

                    <?php if ($pagination['page'] < $pagination['total_pages']): ?>
                        <a href="?page=<?php echo $pagination['page'] + 1; ?><?php echo isset($_GET['age_min']) ? '&age_min=' . $age_min : ''; ?><?php echo isset($_GET['age_max']) ? '&age_max=' . $age_max : ''; ?>" class="btn btn-small">Weiter ›</a>
                        <a href="?page=<?php echo $pagination['total_pages']; ?><?php echo isset($_GET['age_min']) ? '&age_min=' . $age_min : ''; ?><?php echo isset($_GET['age_max']) ? '&age_max=' . $age_max : ''; ?>" class="btn btn-small">Letzte »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
