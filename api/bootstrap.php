<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
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

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $requestHost = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    if (!is_string($originHost) || strtolower($originHost) !== $requestHost) {
        respond(403, ['ok' => false, 'message' => 'İstek kaynağı doğrulanamadı.']);
    }
}

session_name('gokturk_store_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

function rateLimit(string $bucket, int $limit, int $windowSeconds): void
{
    $now = time();
    $key = 'rate_' . $bucket;
    $entry = $_SESSION[$key] ?? ['start' => $now, 'count' => 0];
    if (($now - (int) $entry['start']) >= $windowSeconds) {
        $entry = ['start' => $now, 'count' => 0];
    }
    $entry['count']++;
    $_SESSION[$key] = $entry;
    if ($entry['count'] > $limit) {
        respond(429, ['ok' => false, 'message' => 'Çok fazla deneme yapıldı. Lütfen kısa süre sonra tekrar deneyin.']);
    }
}

function input(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        respond(400, ['ok' => false, 'message' => 'İstek verisi eksik.']);
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
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length > $max) respond(422, ['ok' => false, 'message' => 'Alanlardan biri izin verilen uzunluğu aşıyor.']);
    return $value;
}

function database(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath)) {
        error_log('api/config.php is missing');
        respond(503, ['ok' => false, 'message' => 'Sipariş sistemi henüz yapılandırılmadı.']);
    }
    $config = require $configPath;
    $db = $config['database'] ?? [];
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? '');
        $pdo = new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (Throwable $error) {
        error_log('Database connection error: ' . $error->getMessage());
        respond(503, ['ok' => false, 'message' => 'Sipariş sistemiyle bağlantı kurulamadı.']);
    }
    return $pdo;
}

function normalizePhone(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}
