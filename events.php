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

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Lade Events
$stmt = $pdo->prepare('
    SELECT e.*, u.username, 
        (SELECT COUNT(*) FROM event_members WHERE event_id = e.id AND status = "attending") as attending_count,
        (SELECT COUNT(*) FROM event_members WHERE event_id = e.id AND status = "maybe") as maybe_count
    FROM events e
    JOIN users u ON e.organizer_id = u.id
    WHERE e.start_date > NOW()
    ORDER BY e.start_date ASC
    LIMIT ?, ?
');
$stmt->execute([$offset, $per_page]);
$events = $stmt->fetchAll();

// Zähle Events
$stmt = $pdo->prepare('SELECT COUNT(*) as total FROM events WHERE start_date > NOW()');
$stmt->execute();
$total = $stmt->fetch()['total'] ?? 0;

$pagination = getPagination($page, $per_page, $total);

// Handle Event erstellen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $start_date = sanitize($_POST['start_date'] ?? '');
    $location = sanitize($_POST['location'] ?? '');
    $max_participants = (int)($_POST['max_participants'] ?? 0);

    if (empty($title) || empty($start_date)) {
        $error = 'Titel und Datum erforderlich';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO events (organizer_id, title, description, start_date, location, max_participants, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$current_user_id, $title, $description, $start_date, $location, $max_participants]);
        $success = 'Event erstellt!';
        header('Refresh: 2');
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>🎉 Events</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Event erstellen -->
        <div class="card mb-3">
            <div class="card-header">
                <h3>➕ Neues Event erstellen</h3>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title">Event-Titel</label>
                            <input type="text" id="title" name="title" required>
                        </div>
                        <div class="form-group">
                            <label for="start_date">Datum & Uhrzeit</label>
                            <input type="datetime-local" id="start_date" name="start_date" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="location">Ort</label>
                            <input type="text" id="location" name="location">
                        </div>
                        <div class="form-group">
                            <label for="max_participants">Max. Teilnehmer</label>
                            <input type="number" id="max_participants" name="max_participants" min="0">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Beschreibung</label>
                        <textarea id="description" name="description" placeholder="Event-Details..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Event erstellen</button>
                </form>
            </div>
        </div>

        <!-- Events Liste -->
        <div class="card">
            <div class="card-header">
                <h3>Kommende Events (<?php echo $total; ?>)</h3>
            </div>
            <div class="card-body">
                <?php if (empty($events)): ?>
                    <div class="empty-state">
                        <p>Keine Events geplant</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-2">
                        <?php foreach ($events as $event): ?>
                            <div class="card">
                                <div class="card-body">
                                    <h4><?php echo escape($event['title']); ?></h4>
                                    <p style="color: var(--text-light); font-size: 0.9rem;">
                                        📅 <?php echo date('d.m.Y H:i', strtotime($event['start_date'])); ?>
                                    </p>
                                    <?php if ($event['location']): ?>
                                        <p style="color: var(--text-light); font-size: 0.9rem;">
                                            📍 <?php echo escape($event['location']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p><?php echo escape(substr($event['description'], 0, 80)); ?>...</p>
                                    <div style="margin: 1rem 0; color: var(--text-light); font-size: 0.9rem;">
                                        👤 Von: <?php echo escape($event['username']); ?><br>
                                        ✅ <?php echo $event['attending_count']; ?> Zusagen | 🤔 <?php echo $event['maybe_count']; ?> Vielleicht
                                    </div>
                                    <a href="event-view.php?id=<?php echo $event['id']; ?>" class="btn btn-primary btn-block">Zum Event</a>
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
