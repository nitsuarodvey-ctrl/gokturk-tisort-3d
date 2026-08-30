<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/env.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'message' => 'Bu işlem yalnızca POST isteği kabul eder.']);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 32768) {
    respond(413, ['ok' => false, 'message' => 'İstek boyutu izin verilen sınırı aşıyor.']);
}

$contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
if ($contentType !== 'application/json') {
    respond(415, ['ok' => false, 'message' => 'Geçersiz istek biçimi.']);
}

try {
    $storeConfig = loadStoreConfig();
} catch (Throwable $error) {
    error_log('Environment configuration error: ' . $error->getMessage());
    respond(503, ['ok' => false, 'message' => 'Sipariş sistemi henüz yapılandırılmadı.']);
}

$origin = rtrim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
$urlParts = parse_url($storeConfig['app']['url']);
if (!is_array($urlParts) || !isset($urlParts['scheme'], $urlParts['host']) || !in_array(strtolower($urlParts['scheme']), ['http', 'https'], true)) {
    error_log('APP_URL is invalid.');
    respond(503, ['ok' => false, 'message' => 'Sipariş sistemi henüz yapılandırılmadı.']);
}
$scheme = strtolower($urlParts['scheme']);
$port = isset($urlParts['port']) && !(($scheme === 'https' && $urlParts['port'] === 443) || ($scheme === 'http' && $urlParts['port'] === 80)) ? ':' . $urlParts['port'] : '';
$expectedOrigin = $scheme . '://' . strtolower($urlParts['host']) . $port;
if ($origin !== '' && !hash_equals(strtolower($expectedOrigin), strtolower($origin))) {
    respond(403, ['ok' => false, 'message' => 'İstek kaynağı doğrulanamadı.']);
}

session_name('gokturk_store_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (bool) $storeConfig['app']['session_secure'],
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

function database(): PDO
{
    global $storeConfig;
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $db = $storeConfig['database'];
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']);
        $pdo = new PDO($dsn, $db['user'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    } catch (Throwable $error) {
        error_log('Database connection error: ' . $error->getMessage());
        respond(503, ['ok' => false, 'message' => 'Sipariş sistemiyle bağlantı kurulamadı.']);
    }
    return $pdo;
}

function rateLimit(string $bucket, int $limit, int $windowSeconds): void
{
    global $storeConfig;
    $now = time();
    $sessionKey = 'rate_' . $bucket;
    $entry = $_SESSION[$sessionKey] ?? ['start' => $now, 'count' => 0];
    if (($now - (int) $entry['start']) >= $windowSeconds) $entry = ['start' => $now, 'count' => 0];
    $entry['count']++;
    $_SESSION[$sessionKey] = $entry;
    if ($entry['count'] > $limit) {
        respond(429, ['ok' => false, 'message' => 'Çok fazla deneme yapıldı. Lütfen kısa süre sonra tekrar deneyin.']);
    }

    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $keyHash = hash_hmac('sha256', $remoteAddress, $storeConfig['app']['key']);
    $cutoff = gmdate('Y-m-d H:i:s', $now - $windowSeconds);
    try {
        $pdo = database();
        $statement = $pdo->prepare('INSERT INTO rate_limits (bucket, key_hash, window_started_at, request_count) VALUES (:bucket, :key_hash, UTC_TIMESTAMP(), 1) ON DUPLICATE KEY UPDATE request_count = IF(window_started_at < :cutoff_count, 1, request_count + 1), window_started_at = IF(window_started_at < :cutoff_window, UTC_TIMESTAMP(), window_started_at), updated_at = UTC_TIMESTAMP()');
        $statement->execute(['bucket' => $bucket, 'key_hash' => $keyHash, 'cutoff_count' => $cutoff, 'cutoff_window' => $cutoff]);
        $check = $pdo->prepare('SELECT request_count FROM rate_limits WHERE bucket = :bucket AND key_hash = :key_hash LIMIT 1');
        $check->execute(['bucket' => $bucket, 'key_hash' => $keyHash]);
        if ((int) $check->fetchColumn() > $limit) {
            respond(429, ['ok' => false, 'message' => 'Çok fazla deneme yapıldı. Lütfen kısa süre sonra tekrar deneyin.']);
        }
    } catch (PDOException $error) {
        error_log('Rate limit storage error: ' . $error->getMessage());
        respond(503, ['ok' => false, 'message' => 'İşlem şu anda tamamlanamıyor.']);
    }
}

function input(): array
{
    $raw = file_get_contents('php://input', false, null, 0, 32769);
    if (!is_string($raw) || $raw === '') {
        respond(400, ['ok' => false, 'message' => 'İstek verisi eksik.']);
    }
    if (strlen($raw) > 32768) {
        respond(413, ['ok' => false, 'message' => 'İstek boyutu izin verilen sınırı aşıyor.']);
    }
    try {
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        respond(400, ['ok' => false, 'message' => 'İstek verisi okunamadı.']);
    }
    if (!is_array($data)) respond(400, ['ok' => false, 'message' => 'Geçersiz istek.']);
    return $data;
}

function textValue(array $data, string $key, int $max, bool $required = true): ?string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '') {
        if ($required) respond(422, ['ok' => false, 'message' => 'Lütfen zorunlu alanları eksiksiz doldurun.']);
        return null;
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
        respond(422, ['ok' => false, 'message' => 'Alanlardan biri geçersiz karakter içeriyor.']);
    }
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length > $max) respond(422, ['ok' => false, 'message' => 'Alanlardan biri izin verilen uzunluğu aşıyor.']);
    return $value;
}

function normalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}
