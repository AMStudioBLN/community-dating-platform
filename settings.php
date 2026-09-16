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

// Lade Benutzer und Profilinformationen
$user = getUserById($current_user_id);
$profile = getUserProfile($current_user_id);

// Handle Einstellungen aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_profile') {
        $title = sanitize($_POST['title'] ?? '');
        $about_me = sanitize($_POST['about_me'] ?? '');
        $interests = sanitize($_POST['interests'] ?? '');
        $location = sanitize($_POST['location'] ?? '');
        $looking_for = sanitize($_POST['looking_for'] ?? '');
        $birthdate = sanitize($_POST['birthdate'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');

        $stmt = $pdo->prepare('
            UPDATE user_profiles SET 
                title = ?, about_me = ?, interests = ?, location = ?, 
                looking_for = ?, birthdate = ?, gender = ?
            WHERE user_id = ?
        ');
        $stmt->execute([$title, $about_me, $interests, $location, $looking_for, $birthdate, $gender, $current_user_id]);
        $success = 'Profil aktualisiert';

    } elseif ($_POST['action'] === 'privacy_settings') {
        $show_profile_visitors = isset($_POST['show_profile_visitors']) ? 1 : 0;
        $show_online_status = isset($_POST['show_online_status']) ? 1 : 0;
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        $allow_messages = isset($_POST['allow_messages']) ? 1 : 0;

        $stmt = $pdo->prepare('
            UPDATE user_profiles SET 
                show_profile_visitors = ?, show_online_status = ?, is_public = ?, allow_messages = ?
            WHERE user_id = ?
        ');
        $stmt->execute([$show_profile_visitors, $show_online_status, $is_public, $allow_messages, $current_user_id]);
        $success = 'Datenschutzeinstellungen aktualisiert';

    } elseif ($_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (!password_verify($current_password, $user['password'])) {
            $error = 'Aktuelles Passwort ist falsch';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Neue Passwörter stimmen nicht überein';
        } elseif (strlen($new_password) < 6) {
            $error = 'Passwort muss mindestens 6 Zeichen lang sein';
        } else {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashed, $current_user_id]);
            $success = 'Passwort geändert';
        }

    } elseif ($_POST['action'] === 'delete_account') {
        $confirm_delete = $_POST['confirm_delete'] ?? '';
        if ($confirm_delete === 'DELETE') {
            // Lösche alle Benutzer-Daten
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$current_user_id]);
            
            session_destroy();
            header('Location: index.php');
            exit;
        } else {
            $error = 'Geben Sie "DELETE" ein, um Ihr Konto zu löschen';
        }
    }
    
    // Reload profile
    if (empty($error)) {
        $user = getUserById($current_user_id);
        $profile = getUserProfile($current_user_id);
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--border);
        }
        .settings-tab {
            padding: 1rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: var(--text-light);
            transition: all 0.2s;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
        }
        .settings-tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .settings-content {
            display: none;
        }
        .settings-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container">
        <h1>⚙️ Einstellungen</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="settings-tabs">
            <button class="settings-tab active" onclick="switchTab('profile')">👤 Profil</button>
            <button class="settings-tab" onclick="switchTab('privacy')">🔒 Datenschutz</button>
            <button class="settings-tab" onclick="switchTab('password')">🔑 Passwort</button>
            <button class="settings-tab" onclick="switchTab('account')">⚠️ Konto</button>
        </div>

        <!-- Profil Tab -->
        <div id="profile" class="settings-content active">
            <div class="card">
                <div class="card-header">
                    <h3>Profilinformationen</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Titel / Slogan</label>
                                <input type="text" id="title" name="title" value="<?php echo escape($profile['title']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="location">Wohnort</label>
                                <input type="text" id="location" name="location" value="<?php echo escape($profile['location']); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="gender">Geschlecht</label>
                                <select id="gender" name="gender">
                                    <option value="">-- Nicht angegeben --</option>
                                    <option value="male" <?php echo $profile['gender'] === 'male' ? 'selected' : ''; ?>>Männlich</option>
                                    <option value="female" <?php echo $profile['gender'] === 'female' ? 'selected' : ''; ?>>Weiblich</option>
                                    <option value="other" <?php echo $profile['gender'] === 'other' ? 'selected' : ''; ?>>Sonstiges</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="birthdate">Geburtsdatum</label>
                                <input type="date" id="birthdate" name="birthdate" value="<?php echo escape($profile['birthdate']); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="looking_for">Suche nach</label>
                            <select id="looking_for" name="looking_for">
                                <option value="">-- Keine Angabe --</option>
                                <option value="male" <?php echo $profile['looking_for'] === 'male' ? 'selected' : ''; ?>>Männlich</option>
                                <option value="female" <?php echo $profile['looking_for'] === 'female' ? 'selected' : ''; ?>>Weiblich</option>
                                <option value="both" <?php echo $profile['looking_for'] === 'both' ? 'selected' : ''; ?>>Beide</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="interests">Interessen (kommagetrennt)</label>
                            <textarea id="interests" name="interests" rows="3"><?php echo escape($profile['interests']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="about_me">Über mich</label>
                            <textarea id="about_me" name="about_me" rows="5" placeholder="Erzählen Sie etwas über sich..."><?php echo escape($profile['about_me']); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Speichern</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Datenschutz Tab -->
        <div id="privacy" class="settings-content">
            <div class="card">
                <div class="card-header">
                    <h3>Datenschutzeinstellungen</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="privacy_settings">
                        
                        <div class="form-group checkbox">
                            <input type="checkbox" id="is_public" name="is_public" <?php echo $profile['is_public'] ? 'checked' : ''; ?>>
                            <label for="is_public">Profil öffentlich (in Suchergebnissen sichtbar)</label>
                        </div>

                        <div class="form-group checkbox">
                            <input type="checkbox" id="show_profile_visitors" name="show_profile_visitors" <?php echo $profile['show_profile_visitors'] ? 'checked' : ''; ?>>
                            <label for="show_profile_visitors">Zeige wer mein Profil besucht hat</label>
                        </div>

                        <div class="form-group checkbox">
                            <input type="checkbox" id="show_online_status" name="show_online_status" <?php echo $profile['show_online_status'] ? 'checked' : ''; ?>>
                            <label for="show_online_status">Online-Status anzeigen</label>
                        </div>

                        <div class="form-group checkbox">
                            <input type="checkbox" id="allow_messages" name="allow_messages" <?php echo $profile['allow_messages'] ? 'checked' : ''; ?>>
                            <label for="allow_messages">Nachrichten von jedem empfangen</label>
                        </div>

                        <button type="submit" class="btn btn-primary">Speichern</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Passwort Tab -->
        <div id="password" class="settings-content">
            <div class="card">
                <div class="card-header">
                    <h3>Passwort ändern</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">Aktuelles Passwort</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Neues Passwort</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Passwort bestätigen</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Passwort ändern</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Konto Tab -->
        <div id="account" class="settings-content">
            <div class="card alert-danger">
                <div class="card-header">
                    <h3>⚠️ Gefahrenzone</h3>
                </div>
                <div class="card-body">
                    <h4>Konto löschen</h4>
                    <p>Wenn Sie Ihr Konto löschen, werden alle Ihre Daten gelöscht. Diese Aktion kann nicht rückgängig gemacht werden.</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="delete_account">
                        
                        <div class="form-group">
                            <label for="confirm_delete">Geben Sie "DELETE" ein um Bestätigung</label>
                            <input type="text" id="confirm_delete" name="confirm_delete" placeholder="DELETE">
                        </div>

                        <button type="submit" class="btn btn-danger" onclick="return confirm('Sind Sie wirklich sicher? Dies kann nicht rückgängig gemacht werden!')">⚠️ Konto unwiederbringlich löschen</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.settings-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.settings-tab').forEach(el => el.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
    <script src="js/main.js"></script>
</body>
</html>
