<?php
/**
 * Community-Dating-Platform
 * Konfigurationsdatei
 */

// Fehlerbehandlung
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Dateiverzeichnisse anlegen, falls nicht vorhanden
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
if (!is_dir(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0755, true);
}
if (!is_dir(__DIR__ . '/uploads/profiles')) {
    mkdir(__DIR__ . '/uploads/profiles', 0755, true);
}
if (!is_dir(__DIR__ . '/uploads/gallery')) {
    mkdir(__DIR__ . '/uploads/gallery', 0755, true);
}
if (!is_dir(__DIR__ . '/uploads/groups')) {
    mkdir(__DIR__ . '/uploads/groups', 0755, true);
}
if (!is_dir(__DIR__ . '/uploads/events')) {
    mkdir(__DIR__ . '/uploads/events', 0755, true);
}

// Installationskonfiguration
$config_file = __DIR__ . '/settings.json';
$installed = false;
$db_host = 'localhost';
$db_name = 'community_platform';
$db_user = 'root';
$db_pass = '';

if (file_exists($config_file)) {
    $config_data = json_decode(file_get_contents($config_file), true);
    $db_host = $config_data['db_host'] ?? 'localhost';
    $db_name = $config_data['db_name'] ?? 'community_platform';
    $db_user = $config_data['db_user'] ?? 'root';
    $db_pass = $config_data['db_pass'] ?? '';
    $installed = true;
}

// Konstanten
define('SITE_NAME', 'Community Hub');
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('UPLOADS_URL', SITE_URL . '/uploads');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('SESSION_TIMEOUT', 3600); // 1 Stunde
define('DB_HOST', $db_host);
define('DB_NAME', $db_name);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('INSTALLED', $installed);

// Session-Sicherheit
session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path' => '/',
    'domain' => parse_url(SITE_URL, PHP_URL_HOST),
    'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF-Token generieren
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Benutzer Authentifizierungsstatus
$logged_in = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user = null;

// Fehlermeldungen
$_SESSION['error'] = $_SESSION['error'] ?? null;
$_SESSION['success'] = $_SESSION['success'] ?? null;
?>
