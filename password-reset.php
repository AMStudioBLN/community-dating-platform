<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

if (!INSTALLED) {
    header('Location: install.php');
    exit;
}

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$token = $_GET['token'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRF($_POST['csrf_token'])) {
        $error = 'Sicherheitstoken ungültig';
    } elseif ($step == 1) {
        // Schritt 1: E-Mail eingeben
        $email = sanitize($_POST['email'] ?? '');

        if (empty($email)) {
            $error = 'Bitte geben Sie Ihre E-Mail-Adresse ein';
        } else {
            $user = getUserByEmail($email);
            if (!$user) {
                // Sicherheit: keine Meldung, ob E-Mail existiert
                $success = 'Wenn diese E-Mail-Adresse in unserem System existiert, erhalten Sie einen Passwort-Reset-Link';
            } else {
                // Reset-Token generieren
                $pdo = db();
                $reset_token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 Stunde gültig

                $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())');
                $stmt->execute([$user['id'], $reset_token, $expires_at]);

                // TODO: E-Mail mit Reset-Link senden
                $reset_link = SITE_URL . '/password-reset.php?step=2&token=' . $reset_token;
                // sendEmail($email, 'Passwort zurücksetzen', 'Klicken Sie hier: ' . $reset_link);

                $success = 'Wenn diese E-Mail-Adresse in unserem System existiert, erhalten Sie einen Passwort-Reset-Link';
                logAction($user['id'], 'password_reset_requested', $email, $user['id'], 'user');
            }
        }
    } elseif ($step == 2 && $token) {
        // Schritt 2: Neues Passwort eingeben
        $new_password = $_POST['new_password'] ?? '';
        $new_password_confirm = $_POST['new_password_confirm'] ?? '';

        if (empty($new_password)) {
            $error = 'Bitte geben Sie ein neues Passwort ein';
        } elseif (!isValidPassword($new_password)) {
            $error = 'Passwort muss mindestens 8 Zeichen lang sein';
        } elseif ($new_password !== $new_password_confirm) {
            $error = 'Passwörter stimmen nicht überein';
        } else {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW()');
            $stmt->execute([$token]);
            $reset = $stmt->fetch();

            if (!$reset) {
                $error = 'Der Reset-Link ist ungültig oder abgelaufen';
            } else {
                // Passwort aktualisieren
                $password_hash = hashPassword($new_password);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([$password_hash, $reset['user_id']]);

                // Token löschen
                $stmt = $pdo->prepare('DELETE FROM password_resets WHERE id = ?');
                $stmt->execute([$reset['id']]);

                logAction($reset['user_id'], 'password_reset', '', $reset['user_id'], 'user');

                $success = 'Ihr Passwort wurde erfolgreich zurückgesetzt. Sie können sich jetzt anmelden.';
                $step = 3; // Erfolgsseite
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort zurücksetzen - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <h1><?php echo SITE_NAME; ?></h1>
            <h2>Passwort zurücksetzen</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!empty($success) && $step != 3): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($step == 1): ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                    <p>Geben Sie Ihre E-Mail-Adresse ein, um einen Passwort-Reset-Link zu erhalten.</p>
                    <div class="form-group">
                        <label for="email">E-Mail-Adresse</label>
                        <input type="email" id="email" name="email" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Reset-Link senden</button>
                </form>
            <?php elseif ($step == 2 && $token): ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">
                    <div class="form-group">
                        <label for="new_password">Neues Passwort</label>
                        <input type="password" id="new_password" name="new_password" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="new_password_confirm">Passwort wiederholen</label>
                        <input type="password" id="new_password_confirm" name="new_password_confirm" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Passwort aktualisieren</button>
                </form>
            <?php elseif ($step == 3): ?>
                <div class="success-message">
                    <div class="success-icon">✓</div>
                    <h3>Erfolgreich!</h3>
                    <p><?php echo $success; ?></p>
                    <a href="login.php" class="btn btn-primary btn-block">Zur Anmeldung</a>
                </div>
            <?php else: ?>
                <p>Ungültiger Reset-Link. Bitte versuchen Sie es erneut.</p>
                <a href="password-reset.php" class="btn btn-primary btn-block">Neu versuchen</a>
            <?php endif; ?>

            <div class="auth-links">
                <a href="login.php">Zurück zur Anmeldung</a>
            </div>
        </div>
    </div>
    <script src="js/main.js"></script>
</body>
</html>