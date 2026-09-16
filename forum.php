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
$offset = ($page - 1) * $per_page;

// Lade Forum-Kategorien
$stmt = $pdo->prepare('
    SELECT * FROM forum_categories 
    ORDER BY sort_order ASC
');
$stmt->execute();
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>💬 Community Forum</h1>
        <p>Diskutieren Sie mit anderen Mitgliedern über verschiedene Themen</p>

        <!-- Kategorien -->
        <div class="grid grid-2">
            <?php foreach ($categories as $category): ?>
                <div class="card">
                    <div class="card-body">
                        <h3><?php echo escape($category['name']); ?></h3>
                        <p><?php echo escape($category['description']); ?></p>
                        <div style="color: var(--text-light); font-size: 0.9rem; margin: 1rem 0;">
                            💬 <?php echo $category['post_count']; ?> Beiträge | 📌 <?php echo $category['topic_count']; ?> Themen
                        </div>
                        <a href="forum-category.php?id=<?php echo $category['id']; ?>" class="btn btn-primary btn-block">Zur Kategorie</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Neue Kategorie Button (für Admins) -->
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <div style="margin-top: 2rem; text-align: center;">
                <a href="admin.php" class="btn btn-secondary">⚙️ Kategorien verwalten</a>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>
