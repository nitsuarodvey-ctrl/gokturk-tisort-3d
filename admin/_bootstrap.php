<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/payment-rules.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
header('Cache-Control: no-store');

try {
    $storeConfig = loadStoreConfig();
} catch (Throwable $error) {
    error_log('Admin environment configuration error: ' . $error->getMessage());
    http_response_code(503);
    exit('Yönetim sistemi henüz yapılandırılmadı.');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
        http_response_code(413);
        exit('İstek boyutu izin verilen sınırı aşıyor.');
    }
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/x-www-form-urlencoded') {
        http_response_code(415);
        exit('Geçersiz istek biçimi.');
    }
    $origin = rtrim(strtolower((string) ($_SERVER['HTTP_ORIGIN'] ?? '')), '/');
    if ($origin !== '') {
        $urlParts = parse_url($storeConfig['app']['url']);
        $scheme = strtolower((string) ($urlParts['scheme'] ?? ''));
        $port = isset($urlParts['port']) && !(($scheme === 'https' && $urlParts['port'] === 443) || ($scheme === 'http' && $urlParts['port'] === 80)) ? ':' . $urlParts['port'] : '';
        $expectedOrigin = $scheme . '://' . strtolower((string) ($urlParts['host'] ?? '')) . $port;
        if (!hash_equals($expectedOrigin, $origin)) {
            http_response_code(403);
            exit('İstek kaynağı doğrulanamadı.');
        }
    }
}

$scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin')));
$cookiePath = rtrim($scriptDirectory, '/') . '/';
session_name('gokturk_admin_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookiePath,
    'secure' => (bool) $storeConfig['app']['session_secure'],
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

function adminDb(): PDO
{
    global $storeConfig;
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $db = $storeConfig['database'];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00'");
    return $pdo;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function validCsrf(string $token): bool
{
    return isset($_SESSION['csrf']) && is_string($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

function endAdminSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);
    }
    session_destroy();
}

function requireAdmin(): void
{
    $now = time();
    $created = (int) ($_SESSION['admin_created_at'] ?? 0);
    $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);
    $expired = $created === 0 || ($now - $created) > 28800 || ($now - $lastActivity) > 1800;
    if (empty($_SESSION['admin_authenticated']) || $expired) {
        endAdminSession();
        header('Location: login.php?expired=1');
        exit;
    }
    $_SESSION['admin_last_activity'] = $now;
}

function adminRateLimit(string $email, int $limit = 8, int $windowSeconds = 900): bool
{
    global $storeConfig;
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $keyHash = hash_hmac('sha256', $remoteAddress . '|' . strtolower($email), $storeConfig['app']['key']);
    $cutoff = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
    $pdo = adminDb();
    $statement = $pdo->prepare('INSERT INTO rate_limits (bucket, key_hash, window_started_at, request_count) VALUES (\'admin_login\', :key_hash, UTC_TIMESTAMP(), 1) ON DUPLICATE KEY UPDATE request_count = IF(window_started_at < :cutoff_count, 1, request_count + 1), window_started_at = IF(window_started_at < :cutoff_window, UTC_TIMESTAMP(), window_started_at), updated_at = UTC_TIMESTAMP()');
    $statement->execute(['key_hash' => $keyHash, 'cutoff_count' => $cutoff, 'cutoff_window' => $cutoff]);
    $check = $pdo->prepare('SELECT request_count FROM rate_limits WHERE bucket = \'admin_login\' AND key_hash = :key_hash LIMIT 1');
    $check->execute(['key_hash' => $keyHash]);
    return (int) $check->fetchColumn() <= $limit;
}

function clearAdminRateLimit(string $email): void
{
    global $storeConfig;
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $keyHash = hash_hmac('sha256', $remoteAddress . '|' . strtolower($email), $storeConfig['app']['key']);
    $statement = adminDb()->prepare('DELETE FROM rate_limits WHERE bucket = \'admin_login\' AND key_hash = :key_hash');
    $statement->execute(['key_hash' => $keyHash]);
}
