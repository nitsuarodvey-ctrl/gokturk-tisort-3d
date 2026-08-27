<?php

declare(strict_types=1);

function setEnvValues(string $path, array $values): void
{
    $contents = file_exists($path) ? file_get_contents($path) : '';
    if ($contents === false) {
        throw new RuntimeException("Env dosyası okunamadı: {$path}");
    }

    foreach ($values as $key => $value) {
        $line = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $line, $contents, 1);
        } else {
            $contents .= PHP_EOL.$line;
        }
    }

    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        throw new RuntimeException("Env dosyası yazılamadı: {$path}");
    }
}

function secret(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

$backendRoot = dirname(__DIR__);
$frontendRoot = dirname($backendRoot);
$backendEnv = $backendRoot.DIRECTORY_SEPARATOR.'.env';
$frontendEnv = $frontendRoot.DIRECTORY_SEPARATOR.'.env.local';
$dbPassword = secret();
$internalKey = secret();

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;charset=utf8mb4',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$quotedPassword = $pdo->quote($dbPassword);
$pdo->exec('CREATE DATABASE IF NOT EXISTS gub_merch CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
$pdo->exec("CREATE USER IF NOT EXISTS 'gub_app'@'127.0.0.1' IDENTIFIED BY {$quotedPassword}");
$pdo->exec("ALTER USER 'gub_app'@'127.0.0.1' IDENTIFIED BY {$quotedPassword}");

setEnvValues($backendEnv, [
    'APP_NAME' => '"GUB Merch API"',
    'APP_URL' => 'http://127.0.0.1:8000',
    'DB_CONNECTION' => 'mysql',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'gub_merch',
    'DB_USERNAME' => 'root',
    'DB_PASSWORD' => '',
    'INTERNAL_API_KEY' => $internalKey,
    'CACHE_STORE' => 'database',
    'SESSION_DRIVER' => 'database',
]);

$php = escapeshellarg(PHP_BINARY);
$artisan = escapeshellarg($backendRoot.DIRECTORY_SEPARATOR.'artisan');
passthru("{$php} {$artisan} config:clear", $clearCode);
if ($clearCode !== 0) {
    throw new RuntimeException('Laravel önbelleği temizlenemedi.');
}
passthru("{$php} {$artisan} migrate --force", $migrationCode);
if ($migrationCode !== 0) {
    throw new RuntimeException('MySQL migration tamamlanamadı.');
}

$pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON gub_merch.* TO 'gub_app'@'127.0.0.1'");
$pdo->exec('FLUSH PRIVILEGES');

setEnvValues($backendEnv, [
    'DB_USERNAME' => 'gub_app',
    'DB_PASSWORD' => $dbPassword,
]);
setEnvValues($frontendEnv, [
    'LARAVEL_API_URL' => 'http://127.0.0.1:8000/api/internal/v1',
    'LARAVEL_API_KEY' => $internalKey,
]);
passthru("{$php} {$artisan} config:clear", $finalClearCode);
if ($finalClearCode !== 0) {
    throw new RuntimeException('Son yapılandırma temizliği tamamlanamadı.');
}

echo 'WAMP MySQL ve Laravel yerel bağlantısı güvenli kullanıcıyla hazırlandı.'.PHP_EOL;
