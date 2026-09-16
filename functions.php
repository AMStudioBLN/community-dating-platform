<?php
/**
 * Global Helper Functions
 */

// Sicherheit
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function escape($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getCSRFToken() {
    return $_SESSION['csrf_token'] ?? '';
}

// Benutzerverwaltung
function getUserById($user_id) {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function getUserByUsername($username) {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function getUserByEmail($email) {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch();
}

function getUserProfile($user_id) {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
}

// Datum und Zeit
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

function formatDateTime($date) {
    return date('d.m.Y H:i', strtotime($date));
}

function timeAgo($date) {
    $timestamp = strtotime($date);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'vor ' . $diff . 's';
    } elseif ($diff < 3600) {
        return 'vor ' . floor($diff / 60) . 'm';
    } elseif ($diff < 86400) {
        return 'vor ' . floor($diff / 3600) . 'h';
    } elseif ($diff < 604800) {
        return 'vor ' . floor($diff / 86400) . 'd';
    } else {
        return formatDate($date);
    }
}

// Dateioperationen
function handleImageUpload($file, $destination_dir) {
    if (!isset($file) || $file['error'] != 0) {
        return ['success' => false, 'message' => 'Fehler beim Dateiupload'];
    }

    $file_size = $file['size'];
    $file_type = $file['type'];
    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];

    // Validierung
    if ($file_size > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'Datei zu groß (max. 5MB)'];
    }

    if (!in_array($file_type, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Dateiformat nicht erlaubt'];
    }

    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if (!in_array($file_ext, ALLOWED_IMAGE_EXTENSIONS)) {
        return ['success' => false, 'message' => 'Dateityp nicht erlaubt'];
    }

    // Sicherer Dateiname
    $new_filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
    $upload_path = $destination_dir . '/' . $new_filename;

    // Verzeichnis erstellen, falls nicht vorhanden
    if (!is_dir($destination_dir)) {
        mkdir($destination_dir, 0755, true);
    }

    // MIME-Type Check
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp);
    finfo_close($finfo);

    if (!in_array($mime_type, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Datei-Validierung fehlgeschlagen'];
    }

    if (move_uploaded_file($file_tmp, $upload_path)) {
        // Bild-Optimierung (optional, für bessere Performance)
        chmod($upload_path, 0644);
        return ['success' => true, 'filename' => $new_filename, 'path' => $upload_path];
    }

    return ['success' => false, 'message' => 'Fehler beim Speichern der Datei'];
}

// Validierung
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username);
}

function isValidPassword($password) {
    return strlen($password) >= 8;
}

function calculateAge($birthdate) {
    $birth = new DateTime($birthdate);
    $today = new DateTime();
    $age = $today->diff($birth);
    return $age->y;
}

// Benachrichtigungen
function addNotification($user_id, $type, $related_user_id = null, $message = '', $link = '') {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('
        INSERT INTO notifications (user_id, type, related_user_id, message, link, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    return $stmt->execute([$user_id, $type, $related_user_id, $message, $link]);
}

function getUnreadNotificationCount($user_id) {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

function markNotificationAsRead($notification_id, $user_id) {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    return $stmt->execute([$notification_id, $user_id]);
}

// Logging
function logAction($user_id, $action, $description = '', $target_id = null, $target_type = null) {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('
        INSERT INTO moderation_logs (user_id, action, description, target_id, target_type, ip_address, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return $stmt->execute([$user_id, $action, $description, $target_id, $target_type, $ip_address]);
}

// Rate Limiting
function checkRateLimit($identifier, $limit, $window) {
    $cache_key = 'rate_limit_' . md5($identifier);
    $attempts = $_SESSION[$cache_key] ?? [];
    $now = time();

    // Alte Versuche entfernen
    $attempts = array_filter($attempts, function($timestamp) use ($now, $window) {
        return $timestamp > ($now - $window);
    });

    if (count($attempts) >= $limit) {
        return false;
    }

    $attempts[] = $now;
    $_SESSION[$cache_key] = $attempts;
    return true;
}

// JSON Response
function jsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Pagierung
function getPagination($page, $per_page, $total) {
    $page = max(1, (int)$page);
    $offset = ($page - 1) * $per_page;
    $total_pages = ceil($total / $per_page);
    
    return [
        'page' => $page,
        'per_page' => $per_page,
        'offset' => $offset,
        'total_pages' => $total_pages,
        'total' => $total
    ];
}
?>
