<?php
declare(strict_types=1);

function readEnvironmentFile(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Environment file is missing.');
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Environment file could not be read.');
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $separator = strpos($line, '=');
        if ($separator === false) continue;

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) continue;

        $first = $value[0] ?? '';
        $last = $value !== '' ? $value[strlen($value) - 1] : '';
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }

    return $values;
}

function loadStoreConfig(): array
{
    static $config = null;
    if (is_array($config)) return $config;

    $projectRoot = dirname(__DIR__);
    $externalPath = dirname($projectRoot) . '/.env';
    $projectPath = $projectRoot . '/.env';
    $customPath = getenv('STORE_ENV_FILE');
    $candidates = array_filter([$customPath !== false ? $customPath : null, $externalPath, $projectPath]);
    $environmentPath = null;
    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            $environmentPath = $candidate;
            break;
        }
    }
    if ($environmentPath === null) {
        throw new RuntimeException('Environment file is missing.');
    }

    $env = readEnvironmentFile($environmentPath);
    $required = ['APP_KEY', 'APP_URL', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'ADMIN_EMAIL', 'ADMIN_PASSWORD_HASH'];
    foreach ($required as $key) {
        if (!isset($env[$key]) || trim($env[$key]) === '') {
            throw new RuntimeException('Required environment value is missing: ' . $key);
        }
    }
    if (strlen($env['APP_KEY']) < 32) {
        throw new RuntimeException('APP_KEY must contain at least 32 characters.');
    }

    $environment = strtolower(trim($env['APP_ENV'] ?? 'production'));
    if (!in_array($environment, ['production', 'testing', 'development'], true)) {
        throw new RuntimeException('APP_ENV is invalid.');
    }
    $resolvedEnvironmentPath = realpath($environmentPath);
    $resolvedProjectPath = realpath($projectPath);
    $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $insideDocumentRoot = false;
    if (is_string($resolvedEnvironmentPath) && is_string($documentRoot)) {
        $pathToCompare = DIRECTORY_SEPARATOR === '\\' ? strtolower($resolvedEnvironmentPath) : $resolvedEnvironmentPath;
        $rootToCompare = DIRECTORY_SEPARATOR === '\\' ? strtolower($documentRoot) : $documentRoot;
        $insideDocumentRoot = $pathToCompare === $rootToCompare || str_starts_with($pathToCompare, rtrim($rootToCompare, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }
    if ($environment === 'production' && ($insideDocumentRoot || ($resolvedProjectPath !== false && $resolvedEnvironmentPath === $resolvedProjectPath))) {
        throw new RuntimeException('Production environment file must be stored outside the public project directory.');
    }

    $appUrl = rtrim(trim($env['APP_URL']), '/');
    $urlParts = parse_url($appUrl);
    if ($urlParts === false || !isset($urlParts['scheme'], $urlParts['host']) || !in_array(strtolower($urlParts['scheme']), ['http', 'https'], true)) {
        throw new RuntimeException('APP_URL must be an absolute HTTP(S) URL.');
    }

    $sessionSecure = filter_var($env['SESSION_SECURE'] ?? 'true', FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    if ($sessionSecure === null) {
        throw new RuntimeException('SESSION_SECURE must be true or false.');
    }
    if ($environment === 'production' && (strtolower($urlParts['scheme']) !== 'https' || !$sessionSecure)) {
        throw new RuntimeException('Production requires HTTPS and secure session cookies.');
    }

    $databasePort = filter_var($env['DB_PORT'] ?? '3306', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($databasePort === false) {
        throw new RuntimeException('DB_PORT is invalid.');
    }
    if (!filter_var($env['ADMIN_EMAIL'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('ADMIN_EMAIL is invalid.');
    }
    if ((password_get_info($env['ADMIN_PASSWORD_HASH'])['algo'] ?? null) === null) {
        throw new RuntimeException('ADMIN_PASSWORD_HASH is invalid.');
    }
    foreach (['APP_KEY', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'ADMIN_PASSWORD_HASH'] as $key) {
        if (str_contains(strtoupper($env[$key] ?? ''), 'CHANGE_ME')) {
            throw new RuntimeException($key . ' still contains a placeholder.');
        }
    }

    $paymentEnabled = filter_var($env['KUVEYT_TURK_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    if ($paymentEnabled === null) {
        throw new RuntimeException('KUVEYT_TURK_ENABLED must be true or false.');
    }
    $paymentMode = strtolower(trim($env['KUVEYT_TURK_MODE'] ?? 'test'));
    if (!in_array($paymentMode, ['test', 'production'], true)) {
        throw new RuntimeException('KUVEYT_TURK_MODE must be test or production.');
    }
    $paymentValues = ['KUVEYT_TURK_CUSTOMER_ID', 'KUVEYT_TURK_MERCHANT_ID', 'KUVEYT_TURK_USERNAME', 'KUVEYT_TURK_PASSWORD'];
    if ($paymentEnabled) {
        foreach ($paymentValues as $key) {
            if (!isset($env[$key]) || trim($env[$key]) === '' || str_contains(strtoupper($env[$key]), 'CHANGE_ME')) {
                throw new RuntimeException('Required payment value is missing: ' . $key);
            }
        }
        if (!preg_match('/^[0-9]{1,20}$/', $env['KUVEYT_TURK_CUSTOMER_ID']) || !preg_match('/^[0-9]{1,20}$/', $env['KUVEYT_TURK_MERCHANT_ID'])) {
            throw new RuntimeException('Kuveyt Turk customer or merchant id is invalid.');
        }
        if (!preg_match('/^[A-Za-z0-9]{1,10}$/', $env['KUVEYT_TURK_USERNAME'])) {
            throw new RuntimeException('KUVEYT_TURK_USERNAME is invalid.');
        }
        if ($paymentMode === 'production' && $environment !== 'production') {
            throw new RuntimeException('Production payment mode requires APP_ENV=production.');
        }
        if ($paymentMode === 'test' && $environment === 'production') {
            throw new RuntimeException('Test payment mode requires APP_ENV=testing or development.');
        }
    }

    $paymentUrls = $paymentMode === 'test'
        ? [
            'pay' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelPayGate',
            'provision' => 'https://boatest.kuveytturk.com.tr/boa.virtualpos.services/Home/ThreeDModelProvisionGate',
        ]
        : [
            'pay' => 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelPayGate',
            'provision' => 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home/ThreeDModelProvisionGate',
        ];

    $config = [
        'app' => [
            'environment' => $environment,
            'url' => $appUrl,
            'key' => $env['APP_KEY'],
            'session_secure' => $sessionSecure,
        ],
        'database' => [
            'host' => $env['DB_HOST'],
            'port' => $databasePort,
            'name' => $env['DB_NAME'],
            'user' => $env['DB_USER'],
            'password' => $env['DB_PASSWORD'] ?? '',
        ],
        'admin' => [
            'email' => strtolower($env['ADMIN_EMAIL']),
            'password_hash' => $env['ADMIN_PASSWORD_HASH'],
        ],
        'payment' => [
            'enabled' => $paymentEnabled,
            'mode' => $paymentMode,
            'customer_id' => trim($env['KUVEYT_TURK_CUSTOMER_ID'] ?? ''),
            'merchant_id' => trim($env['KUVEYT_TURK_MERCHANT_ID'] ?? ''),
            'username' => trim($env['KUVEYT_TURK_USERNAME'] ?? ''),
            'password' => $env['KUVEYT_TURK_PASSWORD'] ?? '',
            'pay_url' => $paymentUrls['pay'],
            'provision_url' => $paymentUrls['provision'],
            'callback_url' => $appUrl . '/api/payment-callback.php',
            'return_url' => $appUrl . '/odeme-sonucu.php',
        ],
    ];

    return $config;
}
