<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('Cache-Control: no-store');

session_name('gokturk_admin_session');
session_set_cookie_params(['lifetime' => 0, 'path' => '/admin', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'httponly' => true, 'samesite' => 'Strict']);
session_start();

$configPath = dirname(__DIR__) . '/api/config.php';
if (!is_file($configPath)) {
    http_response_code(503);
    exit('Yönetim sistemi henüz yapılandırılmadı.');
}
$storeConfig = require $configPath;

function adminDb(): PDO
{
    global $storeConfig;
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $db = $storeConfig['database'] ?? [];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'] ?? 'localhost', $db['port'] ?? 3306, $db['name'] ?? '');
    $pdo = new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['password'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    return $pdo;
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrfToken(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function validCsrf(string $token): bool { return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token); }
function requireAdmin(): void { if (empty($_SESSION['admin_authenticated'])) { header('Location: login.php'); exit; } }
