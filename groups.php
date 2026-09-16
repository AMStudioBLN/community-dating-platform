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

// Handle neue Gruppe erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    if (empty($name)) {
        $error = 'Gruppenname erforderlich';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO groups (creator_id, name, description, is_public, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$current_user_id, $name, $description, $is_public]);
        $group_id = $pdo->lastInsertId();

        // Ersteller als Admin hinzufügen
        $stmt = $pdo->prepare('
            INSERT INTO group_members (group_id, user_id, role, joined_at)
            VALUES (?, ?, "admin", NOW())
        ');
        $stmt->execute([$group_id, $current_user_id]);

        $success = 'Gruppe erstellt! Laden Sie jetzt Freunde ein.';
        header('Refresh: 2; url=groups.php?id=' . $group_id);
    }
}

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Lade Gruppen des Benutzers
$stmt = $pdo->prepare('
    SELECT g.* FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = ? AND (g.is_public = 1 OR g.creator_id = ?)
    ORDER BY g.updated_at DESC
    LIMIT ?, ?
');
$stmt->execute([$current_user_id, $current_user_id, $offset, $per_page]);
$groups = $stmt->fetchAll();

// Zähle Gruppen
$stmt = $pdo->prepare('
    SELECT COUNT(*) as total FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE gm.user_id = ?
');
$stmt->execute([$current_user_id]);
$total = $stmt->fetch()['total'] ?? 0;

$pagination = getPagination($page, $per_page, $total);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gruppen - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>👨‍👩‍👧‍👦 Gruppen & Communities</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Neue Gruppe erstellen -->
        <div class="card mb-3">
            <div class="card-header">
                <h3>➕ Neue Gruppe erstellen</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label for="name">Gruppenname</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Beschreibung</label>
                        <textarea id="description" name="description" placeholder="Kurze Beschreibung der Gruppe..."></textarea>
                    </div>
                    <div class="form-group checkbox">
                        <input type="checkbox" id="is_public" name="is_public" checked>
                        <label for="is_public">Öffentliche Gruppe (andere können beitreten)</label>
                    </div>
                    <button type="submit" class="btn btn-primary">Gruppe erstellen</button>
                </form>
            </div>
        </div>

        <!-- Gruppen Liste -->
        <div class="card">
            <div class="card-header">
                <h3>Meine Gruppen (<?php echo count($groups); ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($groups)): ?>
                    <div class="empty-state">
                        <p>Sie sind noch nicht in Gruppen. Erstellen Sie eine oder treten Sie einer bei!</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-2">
                        <?php foreach ($groups as $group): ?>
                            <div class="card">
                                <div class="card-body">
                                    <h4><?php echo escape($group['name']); ?></h4>
                                    <p><?php echo escape(substr($group['description'], 0, 100)); ?>...</p>
                                    <div style="margin: 1rem 0; color: var(--text-light); font-size: 0.9rem;">
                                        👥 <?php echo $group['member_count']; ?> Mitglieder
                                    </div>
                                    <a href="group-view.php?id=<?php echo $group['id']; ?>" class="btn btn-primary btn-block">Zur Gruppe</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($pagination['total_pages'] > 1): ?>
                        <div class="pagination" style="margin-top: 2rem;">
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
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
