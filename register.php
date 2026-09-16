<?php
require_once 'config.php';
require_once 'database.php';
require_once 'functions.php';

if (!INSTALLED) {
    header('Location: install.php');
    exit;
}

// Falls bereits eingeloggt
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRF($_POST['csrf_token'])) {
        $error = 'Sicherheitstoken ungültig';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $birthdate = sanitize($_POST['birthdate'] ?? '');
        $gender = sanitize($_POST['gender'] ?? '');
        $looking_for = sanitize($_POST['looking_for'] ?? '');
        $agree_terms = isset($_POST['agree_terms']);

        // Validierung
        if (empty($username) || empty($email) || empty($password) || empty($birthdate) || empty($gender) || empty($looking_for)) {
            $error = 'Bitte füllen Sie alle erforderlichen Felder aus';
        } elseif (!isValidUsername($username)) {
            $error = 'Benutzername ungültig (3-20 Zeichen, nur Buchstaben, Zahlen, - und _)';
        } elseif (!isValidEmail($email)) {
            $error = 'Ungültige E-Mail-Adresse';
        } elseif (!isValidPassword($password)) {
            $error = 'Passwort muss mindestens 8 Zeichen lang sein';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwörter stimmen nicht überein';
        } elseif (!$agree_terms) {
            $error = 'Bitte akzeptieren Sie die Nutzungsbedingungen';
        } elseif (calculateAge($birthdate) < 18) {
            $error = 'Du musst mindestens 18 Jahre alt sein';
        } else {
            // Prüfe ob Benutzer/Email existiert
            if (getUserByUsername($username)) {
                $error = 'Dieser Benutzername ist bereits vergeben';
            } elseif (getUserByEmail($email)) {
                $error = 'Diese E-Mail-Adresse ist bereits registriert';
            } else {
                // Benutzer erstellen
                $pdo = db();
                $password_hash = hashPassword($password);

                try {
                    $stmt = $pdo->prepare('
                        INSERT INTO users (username, email, password, is_verified, created_at)
                        VALUES (?, ?, ?, 0, NOW())
                    ');
                    $stmt->execute([$username, $email, $password_hash]);
                    $user_id = $pdo->lastInsertId();

                    // Profil erstellen
                    $stmt = $pdo->prepare('
                        INSERT INTO user_profiles (user_id, gender, looking_for, birthdate, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$user_id, $gender, $looking_for, $birthdate]);

                    // Einstellungen erstellen
                    $stmt = $pdo->prepare('
                        INSERT INTO settings (user_id, created_at)
                        VALUES (?, NOW())
                    ');
                    $stmt->execute([$user_id]);

                    logAction($user_id, 'registration', 'Neuer Benutzer registriert', $user_id, 'user');

                    $_SESSION['success_message'] = 'Registrierung erfolgreich! Bitte melden Sie sich an.';
                    header('Location: login.php');
                    exit;
                } catch (Exception $e) {
                    $error = 'Registrierung fehlgeschlagen. Bitte versuchen Sie es später erneut.';
                    error_log('Registration error: ' . $e->getMessage());
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
    <title>Registrieren - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box auth-box-wide">
            <h1><?php echo SITE_NAME; ?></h1>
            <h2>Registrieren</h2>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo getCSRFToken(); ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Benutzername</label>
                        <input type="text" id="username" name="username" required>
                        <small>3-20 Zeichen, nur Buchstaben, Zahlen, - und _</small>
                    </div>
                    <div class="form-group">
                        <label for="email">E-Mail-Adresse</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <input type="password" id="password" name="password" required>
                        <small>Mindestens 8 Zeichen</small>
                    </div>
                    <div class="form-group">
                        <label for="password_confirm">Passwort wiederholen</label>
                        <input type="password" id="password_confirm" name="password_confirm" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="birthdate">Geburtsdatum</label>
                        <input type="date" id="birthdate" name="birthdate" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Geschlecht</label>
                        <select id="gender" name="gender" required>
                            <option value="">-- Bitte wählen --</option>
                            <option value="male">Männlich</option>
                            <option value="female">Weiblich</option>
                            <option value="other">Sonstiges</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="looking_for">Ich suche nach</label>
                    <select id="looking_for" name="looking_for" required>
                        <option value="">-- Bitte wählen --</option>
                        <option value="male">Männern</option>
                        <option value="female">Frauen</option>
                        <option value="both">Beiden</option>
                    </select>
                </div>

                <div class="form-group checkbox">
                    <input type="checkbox" id="agree_terms" name="agree_terms" required>
                    <label for="agree_terms">Ich akzeptiere die <a href="terms.php">Nutzungsbedingungen</a> und <a href="privacy.php">Datenschutzerklärung</a></label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Registrieren</button>
            </form>

            <div class="auth-links">
                <a href="login.php">Bereits registriert? Anmelden</a>
            </div>
        </div>
    </div>
    <script src="js/main.js"></script>
</body>
</html>