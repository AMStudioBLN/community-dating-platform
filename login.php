<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

if (!INSTALLED) {
    header('Location: install.php');
    exit;
}

// Falls bereits eingeloggt, zur Startseite
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token prüfen
    if (!isset($_POST['csrf_token']) || !validateCSRF($_POST['csrf_token'])) {
        $error = 'Sicherheitstoken ungültig';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);

        if (empty($email) || empty($password)) {
            $error = 'Bitte füllen Sie alle Felder aus';
        } else {
            // Rate Limiting
            if (!checkRateLimit('login_' . $_SERVER['REMOTE_ADDR'], 5, 300)) {
                $error = 'Zu viele Loginversuche. Bitte warten Sie 5 Minuten';
            } else {
                $user = getUserByEmail($email);

                if (!$user || !verifyPassword($password, $user['password'])) {
                    $error = 'E-Mail oder Passwort ist falsch';
                    logAction(null, 'failed_login', $email, null, 'login_attempt');
                } elseif (!$user['is_active']) {
                    $error = 'Dieses Konto wurde deaktiviert';
                } elseif ($user['is_blocked']) {
                    $error = 'Dieses Konto wurde blockiert';
                } else {
                    // Login erfolgreich
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];

                    // Letzten Login aktualisieren
                    $pdo = db();
                    $stmt = $pdo->prepare('UPDATE users SET last_login = NOW(), online_status = "online" WHERE id = ?');
                    $stmt->execute([$user['id']]);

                    logAction($user['id'], 'login', '', $user['id'], 'user');

                    // Redirect
                    $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit;
                }
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
    <title>Anmelden - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <h1><?php echo SITE_NAME; ?></h1>
            <h2>Anmelden</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">

                <div class="form-group">
                    <label for="email">E-Mail-Adresse</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Passwort</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group checkbox">
                    <input type="checkbox" id="remember_me" name="remember_me">
                    <label for="remember_me">Angemeldet bleiben</label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
            </form>

            <div class="auth-links">
                <a href="password-reset.php">Passwort vergessen?</a>
                <a href="register.php">Noch kein Konto? Registrieren</a>
            </div>
        </div>
    </div>
    <script src="js/main.js"></script>
</body>
</html>