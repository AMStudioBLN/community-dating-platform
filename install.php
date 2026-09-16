<?php
/**
 * Community-Dating-Platform Installer
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prüfe ob bereits installiert
$config_file = __DIR__ . '/settings.json';
if (file_exists($config_file)) {
    die('<h2>Plattform ist bereits installiert!</h2><p><a href="index.php">Zur Startseite</a></p>');
}

// Verzeichnisse erstellen
$dirs = ['logs', 'uploads', 'uploads/profiles', 'uploads/gallery', 'uploads/groups', 'uploads/events'];
foreach ($dirs as $dir) {
    if (!is_dir(__DIR__ . '/' . $dir)) {
        mkdir(__DIR__ . '/' . $dir, 0755, true);
    }
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Schritt 2: Datenbank verbinden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step == 1) {
    $db_host = sanitize($_POST['db_host'] ?? 'localhost');
    $db_name = sanitize($_POST['db_name'] ?? 'community_platform');
    $db_user = sanitize($_POST['db_user'] ?? 'root');
    $db_pass = $_POST['db_pass'] ?? '';
    $admin_username = sanitize($_POST['admin_username'] ?? '');
    $admin_email = sanitize($_POST['admin_email'] ?? '');
    $admin_password = $_POST['admin_password'] ?? '';
    $admin_password_confirm = $_POST['admin_password_confirm'] ?? '';

    // Validierung
    if (empty($db_host) || empty($db_name) || empty($db_user)) {
        $error = 'Alle Datenbankfelder sind erforderlich!';
    } elseif (empty($admin_username) || empty($admin_email) || empty($admin_password)) {
        $error = 'Alle Admin-Felder sind erforderlich!';
    } elseif ($admin_password !== $admin_password_confirm) {
        $error = 'Passwörter stimmen nicht überein!';
    } elseif (strlen($admin_password) < 8) {
        $error = 'Passwort muss mindestens 8 Zeichen lang sein!';
    } elseif (!filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ungültige E-Mail-Adresse!';
    } else {
        // Verbindung testen
        try {
            $pdo = new PDO(
                'mysql:host=' . $db_host . ';charset=utf8mb4',
                $db_user,
                $db_pass
            );

            // Datenbank erstellen
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db_name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . str_replace('`', '``', $db_name) . '`');

            // Tabellen erstellen
            $sql_file = __DIR__ . '/schema.sql';
            if (!file_exists($sql_file)) {
                $error = 'schema.sql Datei nicht gefunden!';
            } else {
                $sql = file_get_contents($sql_file);
                $pdo->exec($sql);

                // Admin-Benutzer erstellen
                $admin_password_hash = password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare('
                    INSERT INTO users (username, email, password, role, is_verified, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ');
                $stmt->execute([$admin_username, $admin_email, $admin_password_hash, 'admin']);

                // Konfiguration speichern
                $config = [
                    'db_host' => $db_host,
                    'db_name' => $db_name,
                    'db_user' => $db_user,
                    'db_pass' => $db_pass,
                    'site_name' => 'Community Hub',
                    'site_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'],
                    'installed' => true,
                    'installed_at' => date('Y-m-d H:i:s')
                ];

                if (file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                    $success = 'Installation erfolgreich! Weiterleitung...';
                    echo '<script>setTimeout(() => window.location.href = "index.php", 2000);</script>';
                } else {
                    $error = 'Konfiguration konnte nicht gespeichert werden!';
                }
            }
        } catch (PDOException $e) {
            $error = 'Datenbankfehler: ' . $e->getMessage();
        }
    }
}

// Hilfsfunktion
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Hub - Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 500px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #eee;
        }
        .form-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .section-title {
            font-size: 16px;
            color: #333;
            font-weight: 700;
            margin-bottom: 15px;
        }
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        button:active {
            transform: translateY(0);
        }
        .help-text {
            color: #999;
            font-size: 13px;
            margin-top: 5px;
        }
        .info-box {
            background: #f0f4ff;
            border: 1px solid #d0deff;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #333;
        }
        .info-box strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Community Hub</h1>
        <p class="subtitle">Installation & Konfiguration</p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <strong>Fehler:</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <strong>Erfolg:</strong> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <strong>Willkommen!</strong> Bitte füllen Sie alle Felder aus, um die Plattform zu konfigurieren.
        </div>

        <form method="POST">
            <!-- Datenbankeinstellungen -->
            <div class="form-section">
                <div class="section-title">📊 Datenbankeinstellungen</div>

                <div class="form-group">
                    <label for="db_host">Datenbankhost</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                    <div class="help-text">Normalerweise: localhost</div>
                </div>

                <div class="form-group">
                    <label for="db_name">Datenbankname</label>
                    <input type="text" id="db_name" name="db_name" value="community_platform" required>
                    <div class="help-text">Name der MySQL-Datenbank</div>
                </div>

                <div class="form-group">
                    <label for="db_user">Datenbankbenutzer</label>
                    <input type="text" id="db_user" name="db_user" value="root" required>
                    <div class="help-text">Benutzer mit CREATE DATABASE Berechtigung</div>
                </div>

                <div class="form-group">
                    <label for="db_pass">Datenbankpasswort</label>
                    <input type="password" id="db_pass" name="db_pass">
                    <div class="help-text">Leer lassen, wenn kein Passwort gesetzt</div>
                </div>
            </div>

            <!-- Admin-Konto -->
            <div class="form-section">
                <div class="section-title">👨‍💼 Administrator-Konto</div>

                <div class="form-group">
                    <label for="admin_username">Benutzername</label>
                    <input type="text" id="admin_username" name="admin_username" required>
                    <div class="help-text">Admin-Benutzername (3-20 Zeichen)</div>
                </div>

                <div class="form-group">
                    <label for="admin_email">E-Mail-Adresse</label>
                    <input type="email" id="admin_email" name="admin_email" required>
                    <div class="help-text">E-Mail des Administrator-Kontos</div>
                </div>

                <div class="form-group">
                    <label for="admin_password">Passwort</label>
                    <input type="password" id="admin_password" name="admin_password" required>
                    <div class="help-text">Mindestens 8 Zeichen</div>
                </div>

                <div class="form-group">
                    <label for="admin_password_confirm">Passwort wiederholen</label>
                    <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
                    <div class="help-text">Bestätigung des Passworts</div>
                </div>
            </div>

            <button type="submit">✓ Installation durchführen</button>
        </form>
    </div>
</body>
</html>
